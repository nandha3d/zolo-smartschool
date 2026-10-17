<?php

namespace App\Addons\Library\Services;

use App\Addons\Library\Models\LibraryBook;
use App\Addons\Library\Models\LibraryBookCopy;
use App\Addons\Library\Models\LibraryFine;
use App\Addons\Library\Models\LibraryIssue;
use App\Addons\Library\Models\LibraryMember;
use App\Addons\Library\Models\LibraryReservation;
use App\Addons\Library\Models\LibrarySetting;
use App\Models\Holiday;
use App\Models\Students;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Circulation rules: membership, quotas, issue / return / renew, overdue fines and
 * the reservation queue. Every state change runs in a transaction with the copy row
 * locked, so two counters scanning the same barcode cannot both issue it.
 */
class LibraryService
{
    private ?LibrarySetting $settings = null;
    private ?int $schoolId = null;

    /** For console use, where there is no signed-in user to take the school from. */
    public function forSchool(int $schoolId): static
    {
        $this->schoolId = $schoolId;
        $this->settings = null;
        return $this;
    }

    public function settings(): LibrarySetting
    {
        if ($this->settings) {
            return $this->settings;
        }
        $schoolId = $this->schoolId ?? Auth::user()?->school_id;
        $settings = LibrarySetting::where('school_id', $schoolId)->first();
        if (!$settings) {
            $settings = LibrarySetting::create(['school_id' => $schoolId]);
            $settings->refresh();
        }
        return $this->settings = $settings;
    }

    /** The library membership for a user, created on first use. */
    public function memberFor(User $user): LibraryMember
    {
        $member = LibraryMember::where('user_id', $user->id)->first();
        if ($member) {
            return $member;
        }

        $type = $user->hasRole('Student') ? 'Student' : ($user->hasRole('Teacher') ? 'Teacher' : 'Staff');
        return LibraryMember::create([
            'school_id' => $user->school_id ?? Auth::user()?->school_id,
            'user_id' => $user->id,
            'member_type' => $type,
            'membership_number' => 'LIB-' . strtoupper($type[0]) . '-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'status' => 'Active',
        ]);
    }

    /**
     * Resolve what the librarian scanned or typed: a membership number, a student's
     * admission number, an email, a mobile number or a user id.
     */
    public function findMember(string $code): ?LibraryMember
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $member = LibraryMember::where('membership_number', $code)->first();
        if ($member) {
            return $member;
        }

        $userId = Students::where('admission_no', $code)->value('user_id');
        $user = $userId ? User::find($userId) : User::where('email', $code)->orWhere('mobile', $code)->first();
        if (!$user && ctype_digit($code)) {
            $user = User::find((int) $code);
        }
        if (!$user || $user->hasRole(['Guardian', 'Super Admin'])) {
            return null;
        }

        return $this->memberFor($user);
    }

    /** @return array{max_books: int, issue_days: int} */
    public function limitsFor(LibraryMember $member): array
    {
        $s = $this->settings();
        return [
            'max_books' => (int) ($member->max_books_allowed ?? ($member->isStudent() ? $s->student_max_books : $s->staff_max_books)),
            'issue_days' => (int) ($member->issue_duration_days ?? ($member->isStudent() ? $s->student_issue_days : $s->staff_issue_days)),
        ];
    }

    public function memberSummary(LibraryMember $member): array
    {
        $member->loadMissing('user');
        $limits = $this->limitsFor($member);
        $loans = $member->openIssues()->with('copy.book')->orderBy('due_date')->get();
        $unpaid = (float) $member->fines()->where('status', LibraryFine::UNPAID)->sum('amount');
        $class = null;
        if ($member->isStudent()) {
            $student = Students::with('class_section.class', 'class_section.section', 'class_section.medium')->where('user_id', $member->user_id)->first();
            $class = $student?->class_section?->full_name;
        }

        return [
            'id' => $member->id,
            'membership_number' => $member->membership_number,
            'name' => $member->user?->full_name ?? trim(($member->user?->first_name ?? '') . ' ' . ($member->user?->last_name ?? '')),
            'type' => $member->member_type,
            'class' => $class,
            'status' => $member->status,
            'image' => $member->user?->image,
            'loans' => $loans->map(fn(LibraryIssue $i) => $this->issueRow($i))->values(),
            'loan_count' => $loans->count(),
            'max_books' => $limits['max_books'],
            'issue_days' => $limits['issue_days'],
            'unpaid_fines' => round($unpaid, 2),
            'can_borrow' => $member->status === 'Active' && $loans->count() < $limits['max_books'] && $unpaid <= 0,
        ];
    }

    public function issueRow(LibraryIssue $issue): array
    {
        $book = $issue->copy?->book;
        return [
            'id' => $issue->id,
            'title' => $book?->title,
            'author' => $book?->author,
            'barcode' => $issue->copy?->barcode,
            'issue_date' => $issue->issue_date?->toDateString(),
            'due_date' => $issue->due_date?->toDateString(),
            'return_date' => $issue->return_date?->toDateString(),
            'status' => $issue->status,
            'renewal_count' => $issue->renewal_count,
            'overdue_days' => in_array($issue->status, LibraryIssue::OPEN, true) ? $this->overdueDays($issue->due_date, Carbon::today()) : 0,
            'accrued_fine' => in_array($issue->status, LibraryIssue::OPEN, true) ? $this->fineFor($issue->due_date, Carbon::today()) : (float) $issue->late_fine_amount,
        ];
    }

    public function issue(LibraryMember $member, string $barcode, ?int $issuedBy = null): LibraryIssue
    {
        return DB::transaction(function () use ($member, $barcode, $issuedBy) {
            $copy = LibraryBookCopy::where('barcode', trim($barcode))->lockForUpdate()->first();
            if (!$copy) {
                throw new RuntimeException('No copy with this barcode.');
            }

            $summary = $this->memberSummary($member);
            if ($member->status !== 'Active') {
                throw new RuntimeException("Membership is {$member->status}.");
            }
            if ($summary['unpaid_fines'] > 0) {
                throw new RuntimeException('Member has unpaid fines of ' . number_format($summary['unpaid_fines'], 2) . '. Collect them before issuing.');
            }
            if ($summary['loan_count'] >= $summary['max_books']) {
                throw new RuntimeException("Borrowing limit reached ({$summary['max_books']} books).");
            }

            $reservation = null;
            if ($copy->status === LibraryBookCopy::RESERVED) {
                $reservation = LibraryReservation::where('held_copy_id', $copy->id)->where('status', LibraryReservation::READY)->first();
                if (!$reservation || $reservation->member_id !== $member->id) {
                    throw new RuntimeException('This copy is held for another member\'s reservation.');
                }
            } elseif ($copy->status !== LibraryBookCopy::AVAILABLE) {
                throw new RuntimeException("This copy is {$copy->status}.");
            }

            $today = Carbon::today();
            $issue = LibraryIssue::create([
                'school_id' => $copy->school_id,
                'book_copy_id' => $copy->id,
                'member_id' => $member->id,
                'issued_by' => $issuedBy,
                'issue_date' => $today,
                'due_date' => $this->nextWorkingDay($today->copy()->addDays($summary['issue_days'])),
                'status' => LibraryIssue::ISSUED,
            ]);

            $copy->update(['status' => LibraryBookCopy::ISSUED]);
            $reservation?->update(['status' => LibraryReservation::FULFILLED]);
            // A member who had a pending hold on this title no longer needs it.
            LibraryReservation::where('book_id', $copy->book_id)->where('member_id', $member->id)
                ->where('status', LibraryReservation::PENDING)->update(['status' => LibraryReservation::FULFILLED]);

            return $issue;
        });
    }

    /**
     * Return a copy. Charges an overdue fine when past the grace period, then hands the
     * copy to the next reservation in the queue (or back to the shelf).
     *
     * @return array{issue: LibraryIssue, fine: ?LibraryFine, reserved_for: ?string}
     */
    public function returnCopy(string $barcode, ?int $receivedBy = null, ?string $condition = null): array
    {
        return DB::transaction(function () use ($barcode, $receivedBy, $condition) {
            $copy = LibraryBookCopy::where('barcode', trim($barcode))->lockForUpdate()->first();
            if (!$copy) {
                throw new RuntimeException('No copy with this barcode.');
            }
            $issue = LibraryIssue::where('book_copy_id', $copy->id)->whereIn('status', LibraryIssue::OPEN)->lockForUpdate()->first();
            if (!$issue) {
                throw new RuntimeException('This copy is not on loan.');
            }

            $today = Carbon::today();
            $days = $this->overdueDays($issue->due_date, $today);
            $amount = $this->fineFor($issue->due_date, $today);

            $issue->update([
                'status' => LibraryIssue::RETURNED,
                'return_date' => $today,
                'returned_to' => $receivedBy,
                'late_fine_amount' => $amount,
            ]);

            $fine = null;
            if ($amount > 0) {
                $fine = LibraryFine::create([
                    'school_id' => $issue->school_id,
                    'issue_id' => $issue->id,
                    'member_id' => $issue->member_id,
                    'reason' => 'Overdue',
                    'amount' => $amount,
                    'overdue_days' => $days,
                    'per_day_rate' => $this->settings()->fine_per_day,
                    'status' => LibraryFine::UNPAID,
                ]);
            }

            if ($condition && in_array($condition, LibraryBookCopy::CONDITIONS, true)) {
                $copy->condition = $condition;
            }
            $reservedFor = $this->releaseCopy($copy);

            return ['issue' => $issue, 'fine' => $fine, 'reserved_for' => $reservedFor];
        });
    }

    public function renew(LibraryIssue $issue): LibraryIssue
    {
        return DB::transaction(function () use ($issue) {
            $issue = LibraryIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if (!in_array($issue->status, LibraryIssue::OPEN, true)) {
                throw new RuntimeException('Only books on loan can be renewed.');
            }
            if ($issue->renewal_count >= $this->settings()->max_renewals) {
                throw new RuntimeException('Renewal limit reached.');
            }
            if ($this->fineFor($issue->due_date, Carbon::today()) > 0) {
                throw new RuntimeException('Overdue books must be returned (and the fine settled) instead of renewed.');
            }
            $bookId = $issue->copy->book_id;
            if (LibraryReservation::where('book_id', $bookId)->whereIn('status', LibraryReservation::ACTIVE)->exists()) {
                throw new RuntimeException('Another member is waiting for this title.');
            }

            $limits = $this->limitsFor($issue->member);
            $from = $issue->due_date->greaterThan(Carbon::today()) ? $issue->due_date->copy() : Carbon::today();
            $issue->update([
                'due_date' => $this->nextWorkingDay($from->addDays($limits['issue_days'])),
                'renewal_count' => $issue->renewal_count + 1,
                'status' => LibraryIssue::ISSUED,
            ]);
            return $issue;
        });
    }

    /** Mark a loaned copy lost and bill its replacement price. */
    public function markLost(LibraryIssue $issue, ?int $userId = null): LibraryFine
    {
        return DB::transaction(function () use ($issue, $userId) {
            $issue = LibraryIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if (!in_array($issue->status, LibraryIssue::OPEN, true)) {
                throw new RuntimeException('Only books on loan can be marked lost.');
            }
            $copy = $issue->copy;
            $issue->update(['status' => LibraryIssue::LOST, 'return_date' => Carbon::today(), 'returned_to' => $userId]);
            $copy->update(['status' => LibraryBookCopy::LOST]);

            return LibraryFine::create([
                'school_id' => $issue->school_id,
                'issue_id' => $issue->id,
                'member_id' => $issue->member_id,
                'reason' => 'Lost',
                'amount' => (float) ($copy->book->price ?? 0),
                'status' => LibraryFine::UNPAID,
            ]);
        });
    }

    public function reserve(LibraryMember $member, LibraryBook $book): LibraryReservation
    {
        if ($member->status !== 'Active') {
            throw new RuntimeException("Membership is {$member->status}.");
        }
        if (LibraryReservation::where('book_id', $book->id)->where('member_id', $member->id)->whereIn('status', LibraryReservation::ACTIVE)->exists()) {
            throw new RuntimeException('You already have a reservation for this title.');
        }
        $onLoan = LibraryIssue::where('member_id', $member->id)->whereIn('status', LibraryIssue::OPEN)
            ->whereHas('copy', fn($q) => $q->where('book_id', $book->id))->exists();
        if ($onLoan) {
            throw new RuntimeException('This title is already on loan to the member.');
        }
        if ($book->availableCopies()->exists()) {
            throw new RuntimeException('A copy is on the shelf right now; it can be issued directly.');
        }

        return LibraryReservation::create([
            'school_id' => $book->school_id,
            'book_id' => $book->id,
            'member_id' => $member->id,
            'reservation_date' => Carbon::today(),
            'status' => LibraryReservation::PENDING,
        ]);
    }

    public function cancelReservation(LibraryReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $held = $reservation->held_copy_id ? LibraryBookCopy::whereKey($reservation->held_copy_id)->lockForUpdate()->first() : null;
            $reservation->update(['status' => LibraryReservation::CANCELLED]);
            if ($held && $held->status === LibraryBookCopy::RESERVED) {
                $this->releaseCopy($held);
            }
        });
    }

    /**
     * Nightly housekeeping: flag overdue loans with their accrued fine, and expire
     * holds that were not collected in time (passing the copy to the next in line).
     *
     * @return array{overdue: int, expired: int}
     */
    public function refresh(?CarbonInterface $today = null): array
    {
        $today = $today ? Carbon::instance($today)->startOfDay() : Carbon::today();
        $overdue = 0;
        LibraryIssue::whereIn('status', LibraryIssue::OPEN)->where('due_date', '<', $today)->chunkById(200, function ($issues) use ($today, &$overdue) {
            foreach ($issues as $issue) {
                $issue->update(['status' => LibraryIssue::OVERDUE, 'late_fine_amount' => $this->fineFor($issue->due_date, $today)]);
                $overdue++;
            }
        });

        $expired = 0;
        LibraryReservation::where('status', LibraryReservation::READY)->whereDate('expiry_date', '<', $today)->get()
            ->each(function (LibraryReservation $reservation) use (&$expired) {
                DB::transaction(function () use ($reservation) {
                    $reservation->update(['status' => LibraryReservation::EXPIRED]);
                    $copy = LibraryBookCopy::whereKey($reservation->held_copy_id)->lockForUpdate()->first();
                    if ($copy && $copy->status === LibraryBookCopy::RESERVED) {
                        $this->releaseCopy($copy);
                    }
                });
                $expired++;
            });

        return ['overdue' => $overdue, 'expired' => $expired];
    }

    /** Working days late, after the grace period (Sundays and school holidays skipped). */
    public function overdueDays(?CarbonInterface $due, CarbonInterface $upTo): int
    {
        if (!$due || $upTo->lessThanOrEqualTo($due)) {
            return 0;
        }
        $holidays = $this->settings()->skip_holidays ? $this->holidaysBetween($due, $upTo) : [];
        $days = 0;
        for ($d = Carbon::instance($due)->addDay(); $d->lessThanOrEqualTo($upTo); $d->addDay()) {
            if ($this->settings()->skip_holidays && ($d->isSunday() || isset($holidays[$d->toDateString()]))) {
                continue;
            }
            $days++;
        }
        return max(0, $days - (int) $this->settings()->grace_period_days);
    }

    public function fineFor(?CarbonInterface $due, CarbonInterface $upTo): float
    {
        return round($this->overdueDays($due, $upTo) * (float) $this->settings()->fine_per_day, 2);
    }

    /** Give a returned / released copy to the oldest pending reservation, else shelve it. */
    private function releaseCopy(LibraryBookCopy $copy): ?string
    {
        $next = LibraryReservation::with('member.user')->where('book_id', $copy->book_id)
            ->where('status', LibraryReservation::PENDING)->orderBy('reservation_date')->orderBy('id')->first();

        if (!$next) {
            $copy->status = LibraryBookCopy::AVAILABLE;
            $copy->save();
            return null;
        }

        $copy->status = LibraryBookCopy::RESERVED;
        $copy->save();
        $next->update([
            'status' => LibraryReservation::READY,
            'held_copy_id' => $copy->id,
            'expiry_date' => $this->nextWorkingDay(Carbon::today()->addDays((int) $this->settings()->reservation_hold_days)),
            'notified_at' => now(),
        ]);

        $title = $copy->book?->title;
        try {
            send_notification([$next->member->user_id], 'Library', "\"{$title}\" is ready for you to collect.", 'Library');
        } catch (Throwable) {
            // Push is best effort; the hold is recorded either way.
        }

        return $next->member?->user?->full_name;
    }

    private function nextWorkingDay(Carbon $date): Carbon
    {
        if (!$this->settings()->skip_holidays) {
            return $date;
        }
        $holidays = $this->holidaysBetween($date, $date->copy()->addDays(30));
        while ($date->isSunday() || isset($holidays[$date->toDateString()])) {
            $date->addDay();
        }
        return $date;
    }

    /** @return array<string, true> */
    private function holidaysBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        // toBase(): the model's date accessor reformats to the school's display format.
        return Holiday::whereBetween('date', [Carbon::instance($from)->toDateString(), Carbon::instance($to)->toDateString()])
            ->toBase()
            ->pluck('date')
            ->mapWithKeys(fn($d) => [Carbon::parse($d)->toDateString() => true])
            ->all();
    }
}
