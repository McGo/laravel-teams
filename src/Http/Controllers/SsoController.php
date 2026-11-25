<?php

namespace MirkoHaaser\Teams\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Auth\EntraTokenValidator;
use MirkoHaaser\Teams\Exceptions\TeamsTokenInvalid;
use MirkoHaaser\Teams\Identity\ResolvesTeamsIdentity;
use MirkoHaaser\Teams\Sessions\IssuesTabSession;

/**
 * Exchanges the Entra token from a tab for a session of the application.
 * One place accepts Entra tokens; the rest of the API stays as it is.
 */
class SsoController extends Controller
{
    public function __invoke(
        Request $request,
        EntraTokenValidator $validator,
        ResolvesTeamsIdentity $resolver,
        IssuesTabSession $sessions,
    ): JsonResponse {
        $data = $request->validate(['token' => 'required|string|max:8192']);

        try {
            $claims = $validator->validate($data['token']);
        } catch (TeamsTokenInvalid $e) {
            // The reason goes to the log, never to the caller.
            Log::warning('teams.sso.rejected', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Not signed in.'], 401);
        }

        $identity = $resolver->resolve($claims);

        if ($identity === null) {
            Log::info('teams.sso.unlinked', [
                'tid' => $claims['tid'] ?? null,
                'upn' => $claims['preferred_username'] ?? $claims['upn'] ?? null,
            ]);

            return response()->json(['message' => 'No access for this account.'], 403);
        }

        $identity->markSeen();

        return response()->json($sessions->issue($identity));
    }
}
