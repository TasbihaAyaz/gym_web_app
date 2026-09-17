# Fit Gen Check-in (Tauri)

Windows desktop app that shows **live biometric check-in popups + voice** by polling your Fit Generation gym website (local XAMPP or live server).

## Prerequisites (Windows)

1. **Node.js** (already used for this project)
2. **Rust** — `winget install Rustlang.Rustup`
3. **Visual Studio Build Tools 2022** with:
   - Desktop development with C++
   - Windows 10/11 SDK

   Install example:

   ```bat
   winget install --id Microsoft.VisualStudio.2022.BuildTools -e --override "--wait --quiet --add Microsoft.VisualStudio.Workload.VCTools --includeRecommended"
   ```

## Run in development

```bat
cd c:\xampp\htdocs\fit-generation\desktop\checkin-popup
npm install
tauri-env.cmd dev
```

Or after Build Tools are fully installed and `link.exe` / Windows SDK are on PATH:

```bat
npm run tauri dev
```

## Connect to the gym website

1. Open **Fit Gen Check-in**
2. Enter website base URL:
   - Local: `http://localhost/fit-generation`
   - Live: `https://yourdomain.com` (include subfolder if needed)
3. Sign in with a gym admin/staff account
4. Leave the window open on the front desk — check-ins popup with sound

The window stays **always on top**.

## Build Windows installer

```bat
cd c:\xampp\htdocs\fit-generation\desktop\checkin-popup
tauri-env.cmd build
```

Output:

- `src-tauri\target\release\bundle\nsis\`
- `src-tauri\target\release\bundle\msi\`

## Server APIs (already in Laravel)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/desktop/login` | Get API token |
| GET | `/api/desktop/me` | Validate session |
| GET | `/api/desktop/poll-checkin` | Live check-ins |
| POST | `/api/desktop/logout` | Revoke token |

After you move the gym app to your website, set the desktop app URL to that site — no rebuild required for URL changes.
