<?php

namespace App\Http\Controllers;

use App\Domain\Membership\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    /**
     * Download or view sensitive member documents.
     * Enforces strict authorization (Scenario A09):
     * Unauthenticated or unauthorized users are denied.
     */
    public function showMemberDocument(Request $request, Member $member, string $filename)
    {
        $user = $request->user();
        if (!$user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required to access member records.');
        }

        // Only authorized staff roles can view sensitive member documents
        if (!$user->hasAnyRole(['admin', 'manager', 'reception', 'accountant', 'Administrator', 'Manager', 'Reception', 'Accountant'])) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied. You do not have permission to inspect member identification documents.');
        }

        // Return simulated private document stream with strict no-cache headers
        return response("DOCUMENT: Member #{$member->membership_number} - {$filename}", 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
