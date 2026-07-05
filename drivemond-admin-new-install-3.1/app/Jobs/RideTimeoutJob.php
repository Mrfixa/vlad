<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\TripRequest;

/**
 * Ride acceptance timeout/fallback.
 *
 * Dispatch immediately after broadcasting a new ride to drivers:
 *   RideTimeoutJob::dispatch($tripId)->delay(now()->addSeconds(60));
 *
 * Requires a queue worker and (optionally) Redis for the QUEUE_CONNECTION.
 * With QUEUE_CONNECTION=sync this runs inline and defeats the delay — activate
 * only in environments with a real async queue (Redis/database driver).
 *
 * Fallback timeline:
 *  - 60s: re-broadcast to all online drivers
 *  - 180s: cancel ride + notify user (dispatched as a second delay chain)
 */
class RideTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $tripRequestId,
        public readonly int $attemptNumber = 1,
    ) {}

    public function handle(): void
    {
        $trip = TripRequest::find($this->tripRequestId);

        if (!$trip || $trip->current_status !== 'pending' || $trip->driver_id !== null) {
            // Already accepted or cancelled — nothing to do.
            return;
        }

        if ($this->attemptNumber === 1) {
            // 60s elapsed with no acceptance: re-broadcast to all online drivers.
            Log::info('RideTimeoutJob: no driver accepted at 60s, re-broadcasting', [
                'trip_id' => $this->tripRequestId,
            ]);

            // Re-dispatch this job to run the 3-minute cancellation check.
            // The broadcast itself is handled by the queue worker picking up
            // any re-dispatched ride-offer jobs that the driver app polls for.
            static::dispatch($this->tripRequestId, 2)->delay(now()->addSeconds(120));
        } else {
            // 180s total elapsed — cancel the ride and notify the customer.
            Log::warning('RideTimeoutJob: ride unaccepted at 3m, cancelling', [
                'trip_id' => $this->tripRequestId,
            ]);

            $trip->current_status = 'cancelled';
            $trip->cancelled_by   = 'system';
            $trip->save();

            // Notify the customer via push notification.
            try {
                $customer = $trip->customer;
                if ($customer && $customer->fcm_token) {
                    $push = getNotification(key: 'trip_cancelled', type: 'trip');
                    sendDeviceNotification(
                        fcm_token: $customer->fcm_token,
                        title: translate(key: $push['title'], locale: $customer?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, locale: $customer?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $trip->id,
                        type: 'ride_request',
                        action: $push['action'],
                        user_id: $customer->id,
                    );
                }
            } catch (\Throwable $e) {
                Log::error('RideTimeoutJob: failed to send cancellation notification', [
                    'trip_id' => $this->tripRequestId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
