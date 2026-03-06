<?php

namespace App\Http\Middleware;

use App\Repositories\ImpersonationSessionRepository;
use App\Services\ImpersonationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveImpersonation
{
    public function __construct(
        private ImpersonationSessionRepository $sessions,
        private ImpersonationContext $context
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set(null, null);

        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token) {
            $session = $this->sessions->findActiveByTokenId($token->id);
            if ($session) {
                $this->context->set($session->impersonator, $session->id);
                $request->attributes->set('impersonator_id', $session->impersonator_id);
                $request->attributes->set('impersonation_session_id', $session->id);
            }
        }

        return $next($request);
    }
}
