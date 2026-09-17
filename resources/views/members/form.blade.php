@php $isEdit = isset($member) && $member; @endphp
<div class="form-card" style="max-width:920px">
    <form method="POST" action="{{ $isEdit ? route('members.update', $member) : route('members.store') }}" enctype="multipart/form-data">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="upload-row">
            <div class="upload-preview" id="avatar-preview">
                @if($isEdit && $member->avatar_url)
                    <img src="{{ $member->avatar_url }}" alt="{{ $member->full_name }}">
                @else
                    <span class="upload-initials">{{ $isEdit ? $member->initials : 'MP' }}</span>
                @endif
            </div>
            <div class="upload-fields">
                <label>Profile Photo</label>
                <input type="file" name="avatar" id="avatar-input" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                <p class="field-hint">JPG, PNG or WEBP · max 2MB. Shown on dashboard and member lists.</p>
                @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                @if($isEdit && $member->avatar)
                    <label class="switch-row compact" style="margin-top:10px">
                        <input type="checkbox" name="remove_avatar" value="1">
                        <span><strong>Remove current photo</strong></span>
                    </label>
                @endif
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $member->first_name ?? '') }}" required>
                @error('first_name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $member->last_name ?? '') }}">
                @error('last_name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $member->email ?? '') }}">
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $member->phone ?? '') }}">
                @error('phone')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            @if(isset($member) && $member->exists)
                <div class="form-group">
                    <label>Biometric ID</label>
                    <input type="text" class="form-control" value="{{ $member->device_user_id }}" readonly>
                    <small style="display:block;margin-top:4px;font-size:11.5px;color:var(--text-mute)">Auto-generated. Enroll this ID on the ZKTeco K50.</small>
                </div>
            @endif
            <div class="form-group">
                <label>Gender</label>
                <select name="gender" class="form-select">
                    <option value="">Select gender</option>
                    @foreach(['male','female','other'] as $g)
                        <option value="{{ $g }}" @selected(old('gender', $member->gender ?? '') === $g)>{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Trainer</label>
                <select name="trainer_id" class="form-select searchable @error('trainer_id') is-invalid @enderror" data-placeholder="Self training" data-search-placeholder="Search trainer...">
                    <option value="">Self training</option>
                    @foreach($trainers ?? [] as $trainer)
                        <option value="{{ $trainer->id }}" @selected((string) old('trainer_id', $member->trainer_id ?? '') === (string) $trainer->id)>{{ $trainer->full_name }}</option>
                    @endforeach
                </select>
                <p class="field-hint">Leave as Self training if the member has no assigned trainer.</p>
                @error('trainer_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', isset($member) && $member->date_of_birth ? $member->date_of_birth->format('Y-m-d') : '') }}">
            </div>
            <div class="form-group">
                <label>Status <span class="req">*</span></label>
                <select name="status" class="form-select" required>
                    @foreach(['active','pending','leave','cancelled'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $member->status ?? 'pending') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Joined Date</label>
                <input type="date" name="joined_at" class="form-control" value="{{ old('joined_at', isset($member) && $member->joined_at ? $member->joined_at->format('Y-m-d') : now()->format('Y-m-d')) }}">
            </div>
            <div class="form-group">
                <label>Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="{{ old('emergency_contact', $member->emergency_contact ?? '') }}">
            </div>
            <div class="form-group">
                <label>Emergency Phone</label>
                <input type="text" name="emergency_phone" class="form-control" value="{{ old('emergency_phone', $member->emergency_phone ?? '') }}">
            </div>
            <div class="form-group full">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="{{ old('address', $member->address ?? '') }}">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" class="form-textarea" rows="3">{{ old('notes', $member->notes ?? '') }}</textarea>
            </div>
        </div>

        @php
            $plans = $plans ?? collect();
            $subscription = $subscription ?? ($member->activeSubscription ?? null);
            $defaultStart = old('fee_start_date', $subscription?->start_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
            $defaultEnd = old('fee_end_date', $subscription?->end_date?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d'));
        @endphp

        <div class="fee-period-card">
            <div class="card-head" style="margin-bottom:14px">
                <div>
                    <h3 style="font-size:15px">Fee / Membership Period</h3>
                    <p style="font-size:12.5px;color:var(--text-dim);margin-top:3px">Select package and fee validity from date to expiry date</p>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Package</label>
                    <select name="membership_plan_id" id="membership_plan_id" class="form-select searchable @error('membership_plan_id') is-invalid @enderror" data-placeholder="Select package" data-search-placeholder="Search package...">
                        <option value="">No package / later</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}"
                                data-duration="{{ $plan->duration_days }}"
                                data-price="{{ $plan->price }}"
                                data-name="{{ $plan->name }}"
                                @selected((string) old('membership_plan_id', $subscription->membership_plan_id ?? '') === (string) $plan->id)>
                                {{ $plan->name }} — {{ money($plan->price) }} / {{ $plan->duration_days }} days
                            </option>
                        @endforeach
                    </select>
                    @error('membership_plan_id')<span class="field-error">{{ $message }}</span>@enderror
                    <p class="field-hint">Selecting a package records fee revenue. Split as paid + discount + balance.</p>
                </div>
                <div class="form-group">
                    <label>Amount paid ({{ currency_symbol() }})</label>
                    <input type="number" step="0.01" min="0" name="fee_amount_paid" id="fee_amount_paid" class="form-control @error('fee_amount_paid') is-invalid @enderror" value="{{ old('fee_amount_paid', $subscription->amount_paid ?? '') }}" placeholder="Cash received">
                    @error('fee_amount_paid')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Discount ({{ currency_symbol() }})</label>
                    @php
                        $oldDiscount = old('fee_discount');
                        $oldBalance = old('fee_balance');
                        if ($oldDiscount === null && $subscription?->plan) {
                            $oldBalance = $oldBalance ?? (float) ($subscription->balance_due ?? 0);
                            $oldDiscount = max(0, (float) $subscription->plan->price - (float) $subscription->amount_paid - (float) $oldBalance);
                        }
                    @endphp
                    <input type="number" step="0.01" min="0" name="fee_discount" id="fee_discount" class="form-control @error('fee_discount') is-invalid @enderror" value="{{ $oldDiscount ?? '' }}" placeholder="0">
                    @error('fee_discount')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Balance ({{ currency_symbol() }})</label>
                    <input type="number" step="0.01" min="0" name="fee_balance" id="fee_balance" class="form-control @error('fee_balance') is-invalid @enderror" value="{{ $oldBalance ?? old('fee_balance', $subscription->balance_due ?? '') }}" placeholder="0">
                    @error('fee_balance')<span class="field-error">{{ $message }}</span>@enderror
                    <p class="field-hint" id="fee-discount-hint">Package = amount paid + discount + balance due.</p>
                </div>
                <div class="form-group">
                    <label>Subscription Status</label>
                    <select name="subscription_status" class="form-select">
                        @foreach(['active','pending','leave','cancelled'] as $st)
                            <option value="{{ $st }}" @selected(old('subscription_status', $subscription->status ?? 'active') === $st)>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee From Date</label>
                    <input type="date" name="fee_start_date" id="fee_start_date" class="form-control @error('fee_start_date') is-invalid @enderror" value="{{ $defaultStart }}">
                    @error('fee_start_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Fee Expiry Date</label>
                    <input type="date" name="fee_end_date" id="fee_end_date" class="form-control @error('fee_end_date') is-invalid @enderror" value="{{ $defaultEnd }}">
                    @error('fee_end_date')<span class="field-error">{{ $message }}</span>@enderror
                    <p class="field-hint" id="fee-period-hint">End date auto-fills from package duration; you can change it.</p>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('members.index') }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Member' : 'Save Member' }}</button>
        </div>
    </form>
</div>
<script>
document.getElementById('avatar-input')?.addEventListener('change', function (e) {
    const file = e.target.files?.[0];
    const box = document.getElementById('avatar-preview');
    if (!file || !box) return;
    const url = URL.createObjectURL(file);
    box.innerHTML = '<img src="' + url + '" alt="Preview">';
});

(function () {
    const planSelect = document.getElementById('membership_plan_id');
    const startInput = document.getElementById('fee_start_date');
    const endInput = document.getElementById('fee_end_date');
    const paidInput = document.getElementById('fee_amount_paid');
    const discountInput = document.getElementById('fee_discount');
    const balanceInput = document.getElementById('fee_balance');
    const discountHint = document.getElementById('fee-discount-hint');
    const hint = document.getElementById('fee-period-hint');
    let syncing = false;

    function addDays(dateStr, days) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + Number(days || 0));
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    function packagePrice() {
        const opt = planSelect?.selectedOptions?.[0];
        return opt?.value ? Number(opt.dataset.price || 0) : 0;
    }

    function num(el) {
        const v = parseFloat(el?.value || '');
        return Number.isFinite(v) ? v : 0;
    }

    function updateHint() {
        const price = packagePrice();
        if (!discountHint || !price) return;
        const paid = num(paidInput);
        const discount = num(discountInput);
        const balance = num(balanceInput);
        const sum = paid + discount + balance;
        const ok = Math.abs(sum - price) < 0.01;
        discountHint.textContent =
            'Package ' + price.toFixed(0)
            + ' = paid ' + paid.toFixed(0)
            + ' + discount ' + discount.toFixed(0)
            + ' + balance ' + balance.toFixed(0)
            + (ok ? '.' : ' (check: currently ' + sum.toFixed(0) + ').');
    }

    /** Keep discount & balance independent; paid fills the rest. */
    function syncPaidFromDiscountBalance() {
        if (syncing) return;
        const price = packagePrice();
        if (!price || !paidInput) return;
        syncing = true;
        const discount = num(discountInput);
        const balance = num(balanceInput);
        paidInput.value = Math.max(0, price - discount - balance).toFixed(2);
        syncing = false;
        updateHint();
    }

    /** When cash received changes, keep discount and put remainder into balance. */
    function syncBalanceFromPaid() {
        if (syncing) return;
        const price = packagePrice();
        if (!price || !balanceInput) return;
        syncing = true;
        const paid = num(paidInput);
        const discount = num(discountInput);
        balanceInput.value = Math.max(0, price - paid - discount).toFixed(2);
        syncing = false;
        updateHint();
    }

    function syncEndFromPlan() {
        const opt = planSelect?.selectedOptions?.[0];
        const price = packagePrice();
        if (opt?.value && price && paidInput) {
            const blank =
                (paidInput.value === '' || paidInput.value === null)
                && (discountInput?.value === '' || discountInput?.value === null)
                && (balanceInput?.value === '' || balanceInput?.value === null);
            if (blank) {
                paidInput.value = price.toFixed(2);
                if (discountInput) discountInput.value = '0.00';
                if (balanceInput) balanceInput.value = '0.00';
            } else {
                syncPaidFromDiscountBalance();
            }
        }
        updateHint();
        if (!opt?.value || !startInput?.value) return;
        const duration = Number(opt.dataset.duration || 30);
        endInput.value = addDays(startInput.value, duration);
        if (hint) hint.textContent = 'Auto-set to ' + duration + ' days from start. You can change the expiry date.';
    }

    planSelect?.addEventListener('change', syncEndFromPlan);
    startInput?.addEventListener('change', syncEndFromPlan);
    paidInput?.addEventListener('input', syncBalanceFromPaid);
    discountInput?.addEventListener('input', syncPaidFromDiscountBalance);
    balanceInput?.addEventListener('input', syncPaidFromDiscountBalance);
    updateHint();
})();
</script>
