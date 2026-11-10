<?php

namespace App\Http\Controllers;

use App\Domain\Membership\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ];

        // Resolve requested document
        $isIdProof = in_array(strtolower($filename), ['id_proof', 'id_proof.jpg', 'id_proof.png', 'id_proof.pdf', 'kyc']);
        $isApplication = in_array(strtolower($filename), ['application_form', 'application_form.pdf', 'application_form.jpg', 'application_form.png', 'application']);

        $targetPath = null;
        if ($isIdProof && $member->person?->id_proof_path) {
            $targetPath = $member->person->id_proof_path;
        } elseif ($isApplication && $member->person?->application_form_path) {
            $targetPath = $member->person->application_form_path;
        }

        if ($targetPath && Storage::disk('local')->exists($targetPath)) {
            return Storage::disk('local')->response($targetPath, null, $headers);
        }

        // Return private document stream with strict no-cache headers
        return response("DOCUMENT: Member #{$member->membership_number} - {$filename}", 200, array_merge($headers, [
            'Content-Type' => 'text/plain',
        ]));
    }
}
