use serde_json::Value;
use tauri::{AppHandle, Emitter, Manager, PhysicalPosition, WebviewWindow};

fn join_api(base_url: &str, path: &str) -> Result<String, String> {
    let base = base_url.trim().trim_end_matches('/');
    if base.is_empty() {
        return Err("Server URL is required.".into());
    }
    if !(base.starts_with("http://") || base.starts_with("https://")) {
        return Err("Server URL must start with http:// or https://".into());
    }
    let path = path.trim_start_matches('/');
    Ok(format!("{base}/{path}"))
}

fn client() -> Result<reqwest::Client, String> {
    use std::sync::OnceLock;
    static CLIENT: OnceLock<reqwest::Client> = OnceLock::new();

    if let Some(c) = CLIENT.get() {
        return Ok(c.clone());
    }

    let built = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(15))
        .pool_idle_timeout(std::time::Duration::from_secs(90))
        .pool_max_idle_per_host(4)
        .tcp_keepalive(std::time::Duration::from_secs(30))
        .build()
        .map_err(|e| e.to_string())?;

    let _ = CLIENT.set(built.clone());
    Ok(CLIENT.get().cloned().unwrap_or(built))
}

fn toast_window(app: &AppHandle) -> Result<WebviewWindow, String> {
    app.get_webview_window("toast")
        .ok_or_else(|| "Toast window missing".to_string())
}

fn position_toast_bottom_right(toast: &WebviewWindow) -> Result<(), String> {
    let monitor = toast
        .current_monitor()
        .map_err(|e| e.to_string())?
        .or(toast.primary_monitor().map_err(|e| e.to_string())?)
        .ok_or_else(|| "No monitor found".to_string())?;

    let scale = monitor.scale_factor();
    let mon_size = monitor.size();
    let mon_pos = monitor.position();
    let win_size = toast.outer_size().unwrap_or(tauri::PhysicalSize::new(
        (400.0 * scale) as u32,
        (340.0 * scale) as u32,
    ));

    let margin = (18.0 * scale).round() as i32;
    let x = mon_pos.x + mon_size.width as i32 - win_size.width as i32 - margin;
    let y = mon_pos.y + mon_size.height as i32 - win_size.height as i32 - margin;

    toast
        .set_position(PhysicalPosition::new(x.max(mon_pos.x), y.max(mon_pos.y)))
        .map_err(|e| e.to_string())?;

    Ok(())
}

#[tauri::command]
async fn show_checkin_toast(
    app: AppHandle,
    punch: Value,
    sounds: Option<Value>,
) -> Result<(), String> {
    let toast = toast_window(&app)?;
    let payload = serde_json::json!({
        "punch": punch,
        "sounds": sounds.unwrap_or(serde_json::json!({})),
    });
    let json = serde_json::to_string(&payload).map_err(|e| e.to_string())?;
    let script = format!(
        "try {{ window.__showCheckin && window.__showCheckin({json}); }} catch (e) {{ console.error(e); }}"
    );

    let _ = toast.set_always_on_top(true);
    let _ = position_toast_bottom_right(&toast);
    toast.show().map_err(|e| e.to_string())?;
    let _ = toast.unminimize();
    let _ = toast.set_focus();

    // Direct JS injection is more reliable than events alone
    let _ = toast.eval(&script);
    tokio::time::sleep(std::time::Duration::from_millis(150)).await;
    let _ = position_toast_bottom_right(&toast);
    let _ = toast.set_always_on_top(true);
    let _ = toast.eval(&script);
    let _ = app.emit_to("toast", "checkin-show", payload);

    Ok(())
}

#[tauri::command]
async fn hide_checkin_toast(app: AppHandle) -> Result<(), String> {
    if let Some(toast) = app.get_webview_window("toast") {
        let _ = toast.hide();
    }
    Ok(())
}

#[tauri::command]
async fn desktop_login(
    base_url: String,
    email: String,
    password: String,
    device_name: Option<String>,
) -> Result<Value, String> {
    let url = join_api(&base_url, "api/desktop/login")?;
    let client = client()?;
    let device = device_name.unwrap_or_else(|| "Fit Gen Desktop".into());

    let res = client
        .post(&url)
        .header("Accept", "application/json")
        .json(&serde_json::json!({
            "email": email,
            "password": password,
            "device_name": device,
        }))
        .send()
        .await
        .map_err(|e| format!("Login request failed: {e}"))?;

    let status = res.status();
    let body = res
        .json::<Value>()
        .await
        .map_err(|e| format!("Invalid login response: {e}"))?;

    if !status.is_success() {
        let msg = body
            .pointer("/errors/email/0")
            .or_else(|| body.get("message"))
            .and_then(|v| v.as_str())
            .unwrap_or("Login failed");
        return Err(msg.to_string());
    }

    Ok(body)
}

#[tauri::command]
async fn desktop_me(base_url: String, token: String) -> Result<Value, String> {
    let url = join_api(&base_url, "api/desktop/me")?;
    let client = client()?;

    let res = client
        .get(&url)
        .header("Accept", "application/json")
        .bearer_auth(token)
        .send()
        .await
        .map_err(|e| format!("Session check failed: {e}"))?;

    let status = res.status();
    let body = res
        .json::<Value>()
        .await
        .map_err(|e| format!("Invalid session response: {e}"))?;

    if !status.is_success() {
        return Err("Session expired. Please sign in again.".into());
    }

    Ok(body)
}

#[tauri::command]
async fn desktop_poll(
    base_url: String,
    token: String,
    after_id: u64,
    bootstrap: bool,
) -> Result<Value, String> {
    let path = if bootstrap {
        format!("api/desktop/poll-checkin?bootstrap=1&after_id={after_id}")
    } else {
        format!("api/desktop/poll-checkin?after_id={after_id}&limit=40")
    };
    let url = join_api(&base_url, &path)?;
    let client = client()?;

    let res = client
        .get(&url)
        .header("Accept", "application/json")
        .bearer_auth(&token)
        .send()
        .await
        .map_err(|e| format!("Poll failed: {e}"))?;

    let status = res.status();
    let body = res
        .json::<Value>()
        .await
        .map_err(|e| format!("Invalid poll response: {e}"))?;

    if status.as_u16() == 401 || status.as_u16() == 403 {
        return Err("unauthorized".into());
    }
    if status.as_u16() == 429 {
        return Err("too many requests".into());
    }
    if !status.is_success() {
        let msg = body
            .get("message")
            .and_then(|v| v.as_str())
            .unwrap_or("Poll failed");
        return Err(msg.to_string());
    }

    Ok(body)
}

#[tauri::command]
async fn desktop_live_sync(base_url: String, token: String, after_id: u64) -> Result<Value, String> {
    let url = join_api(
        &base_url,
        &format!("api/desktop/live-sync?after_id={after_id}"),
    )?;
    let client = client()?;

    let res = client
        .post(&url)
        .header("Accept", "application/json")
        .bearer_auth(&token)
        .timeout(std::time::Duration::from_secs(45))
        .send()
        .await
        .map_err(|e| format!("Live sync failed: {e}"))?;

    let status = res.status();
    let body = res
        .json::<Value>()
        .await
        .unwrap_or_else(|_| serde_json::json!({ "ok": false }));

    if status.as_u16() == 401 || status.as_u16() == 403 {
        return Err("unauthorized".into());
    }
    if !status.is_success() {
        return Err("Live sync failed".into());
    }

    Ok(body)
}

#[tauri::command]
async fn desktop_logout(base_url: String, token: String) -> Result<Value, String> {
    let url = join_api(&base_url, "api/desktop/logout")?;
    let client = client()?;

    let _ = client
        .post(&url)
        .header("Accept", "application/json")
        .bearer_auth(token)
        .send()
        .await;

    Ok(serde_json::json!({ "ok": true }))
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![
            desktop_login,
            desktop_me,
            desktop_poll,
            desktop_live_sync,
            desktop_logout,
            show_checkin_toast,
            hide_checkin_toast
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
