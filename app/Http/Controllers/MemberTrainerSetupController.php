<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberTrainerHistory;
use App\Models\Trainer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberTrainerSetupController extends Controller
{
    public function index(Request $request): View
    {
        $member = null;
        if ($request->filled('member_id')) {
            $member = Member::with([
                'trainer',
                'trainerHistories' => fn ($q) => $q->with(['trainer', 'changer'])->latest('id'),
            ])->find($request->integer('member_id'));
        }

        return view('members.trainer-setup', [
            'member' => $member,
            'trainers' => Trainer::where('status', 'active')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json(['members' => []]);
        }

        $members = Member::query()
            ->with('trainer')
            ->where(function ($query) use ($q) {
                $query->where('device_user_id', 'like', "%{$q}%")
                    ->orWhere('member_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) like ?", ["%{$q}%"]);
            })
            ->orderByRaw("CAST(NULLIF(device_user_id, '') AS UNSIGNED) DESC")
            ->limit(20)
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->full_name,
                'bio_id' => $m->device_user_id ?: '',
                'code' => $m->member_code,
                'phone' => $m->phone,
                'trainer' => $m->trainer?->full_name ?? 'NONE',
            ]);

        return response()->json(['members' => $members]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'trainer_id' => ['nullable', 'exists:trainers,id'],
            'trainer_fee' => ['nullable', 'numeric', 'min:0'],
            'trainer_commission' => ['nullable', 'numeric', 'min:0'],
            'gym_commission' => ['nullable', 'numeric', 'min:0'],
            'change_type' => ['required', 'in:change,assign,remove,transfer'],
            'effective_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $member = Member::with('trainer')->findOrFail($data['member_id']);
        $previousName = $member->trainer?->full_name;
        $trainerId = $data['trainer_id'] ?: null;
        $trainerFee = round((float) ($data['trainer_fee'] ?? 0), 2);
        $trainerCommission = round((float) ($data['trainer_commission'] ?? 0), 2);
        $gymCommission = round((float) ($data['gym_commission'] ?? 0), 2);

        if (! $trainerId) {
            $trainerFee = 0;
            $trainerCommission = 0;
            $gymCommission = 0;
            if (($data['change_type'] ?? '') === 'change') {
                $data['change_type'] = 'remove';
            }
        }

        DB::transaction(function () use (
            $member,
            $trainerId,
            $previousName,
            $trainerFee,
            $trainerCommission,
            $gymCommission,
            $data,
        ) {
            $member->update([
                'trainer_id' => $trainerId,
                'trainer_fee' => $trainerFee,
                'trainer_commission' => $trainerCommission,
                'gym_commission' => $gymCommission,
            ]);

            MemberTrainerHistory::create([
                'member_id' => $member->id,
                'trainer_id' => $trainerId,
                'previous_trainer_name' => $previousName,
                'trainer_fee' => $trainerFee,
                'trainer_commission' => $trainerCommission,
                'gym_commission' => $gymCommission,
                'change_type' => $data['change_type'],
                'effective_date' => $data['effective_date'],
                'remarks' => $data['remarks'] ?? null,
                'changed_by' => auth()->id(),
            ]);
        });

        $label = $trainerId
            ? (Trainer::find($trainerId)?->full_name ?? 'trainer')
            : 'Self training (NONE)';

        return redirect()
            ->route('members.trainer-setup', ['member_id' => $member->id])
            ->with('success', 'Trainer updated for '.$member->full_name.' → '.$label.'.');
    }
}
