<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\SubscriptionBill;
use App\Repositories\School\SchoolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FeaturesService {
    public function __construct() {
        // $this->features = app(UserInterface::class)->features();
    }

    /**
     * Check if Animazon Licensing System is active (default true).
     * Under the Animazon enterprise license, all features across the platform are permanently unlocked.
     */
    public static function isAnimazonLicenseActive(): bool {
        return config('app.animazon_license', true);
    }

    public static function getFeatures($schoolID = null) {
        // Under Animazon Licensing System, all features are fully available
        if (self::isAnimazonLicenseActive()) {
            try {
                return Feature::pluck('name', 'id')->toArray();
            } catch (\Throwable $e) {
                return [
                    1 => 'Student Management',
                    2 => 'Academics Management',
                    3 => 'Slider Management',
                    4 => 'Teacher Management',
                    5 => 'Session Year Management',
                    6 => 'Holiday Management',
                    7 => 'Timetable Management',
                    8 => 'Attendance Management',
                    9 => 'Exam Management',
                    10 => 'Lesson Management',
                    11 => 'Assignment Management',
                    12 => 'Announcement Management',
                    13 => 'Staff Management',
                    14 => 'Expense Management',
                    15 => 'Staff Leave Management',
                    16 => 'Fees Management',
                    17 => 'School Gallery Management',
                    18 => 'ID Card - Certificate Generation',
                    19 => 'Website Management',
                    20 => 'Chat Module',
                    21 => 'Transportation Module',
                    22 => 'Staff Attendance Management',
                    23 => 'ID Card Photo Booth',
                    24 => 'GPS Transport Tracking',
                ];
            }
        }

        // Fetch All the Features of the School in which User is associated. Then Cache that result for 30 minutes
        // Null-safe: this is reached with no authenticated user during view compilation
        // and on guest-facing pages, where it previously fataled on ->school_id.
        $schoolID = !empty($schoolID) ? $schoolID : Auth::user()?->school_id;
        if (!empty($schoolID)) {
            return app(CachingService::class)->schoolLevelCaching(config('constants.CACHE.SCHOOL.FEATURES'), function () use ($schoolID) {
                $active_subscription = app(SubscriptionService::class)->active_subscription($schoolID);
                if ($active_subscription) {
                    
                    // Check any outstanding subscription bills
                    $today_date = Carbon::now()->format('Y-m-d');
                    $subscriptionBill = SubscriptionBill::with(['subscription' => function($q) {
                        $q->where('package_type',1);
                    }])->where('school_id',$schoolID)->whereHas('transaction', function($q) {
                        $q->whereNot('payment_status',"succeed");
                    })->where('due_date','<',$today_date)->first();

                    // If null outstanding subscription bills then continue
                    if (!$subscriptionBill) {
                        $packageFeatures = $addon = [];
                        if (!empty($active_subscription->subscription_feature)) {
                            $packageFeatures = $active_subscription->subscription_feature->pluck('feature_id')->toArray();
                        }
                        
                        if (!empty($active_subscription->addons)) {
                            $addon = $active_subscription->addons->pluck('feature_id')->toArray();
                        }

                        $features = array_merge($packageFeatures, $addon);
                        if (!empty($features)) {
                            return Feature::whereIn('id', array_unique($features))->pluck('name', 'id')->toArray();
                        }
                    }

                    
                }
                return [];
            }, $schoolID);
        }

        return [];
    }

    /**
     * @param $argument
     * @return bool
     */
    public static function hasFeature($argument) {
        if (self::isAnimazonLicenseActive()) {
            return true;
        }
        $features = self::getFeatures();
        return in_array($argument, $features);
    }

    /**
     * @param array $argument
     * @return bool
     */
    public static function hasAnyFeature(array $argument) {
        if (self::isAnimazonLicenseActive()) {
            return true;
        }
        $features = self::getFeatures();
        return !empty(array_intersect($argument, $features));
    }

    /**
     * @param array $argument
     * @return bool
     */
    public static function hasAllFeature(array $argument) {
        if (self::isAnimazonLicenseActive()) {
            return true;
        }
        $features = self::getFeatures();

        return empty(array_diff($argument, $features));
    }
}
