<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ReopenMandateRequest;
use App\Models\Residence;
use Illuminate\Http\JsonResponse;

class MandateController extends Controller
{
    /**
     * The "emergency door" from the syndic-transition design: lifting a
     * financial lock is never self-service for a residence's own users —
     * only the Atlasoft team can do it, and only with a recorded reason.
     * This does not restore the deactivated accounts' write access; that's
     * a deliberate separate step so support isn't accidentally undoing the
     * handover itself, only unfreezing the records to fix a mistake.
     */
    public function reopen(ReopenMandateRequest $request, Residence $residence): JsonResponse
    {
        $lock = $residence->currentMandateLock();

        abort_unless($lock, 404, 'Cette résidence n\'a aucune clôture de syndic active.');

        $lock->update([
            'reopened_at' => now(),
            'reopened_by_user_id' => $request->user()->id,
            'reopened_reason' => $request->validated('reason'),
        ]);

        return response()->json(['data' => $lock->fresh()]);
    }
}
