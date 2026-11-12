<?php

namespace App\Http\Controllers;

use App\Domain\Membership\Models\Dependent;
use App\Domain\Membership\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    private const STAFF_PHOTO_PERMISSIONS = [
        'members.view',
        'members.manage',
        'members.kyc_view',
        'counter.operate',
    ];

    /**
     * Download or view sensitive member documents or verification portrait photos.
     * Enforces strict authorization (Scenario A09):
     * - ID proof & application scans stay locked under `members.kyc_view`.
     * - Member photos are viewable by staff with member/POS verification clearance.
     */
    public function showMemberDocument(Request $request, Member $member, string $filename)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required to access member records.');
        }

        $headers = $this->privateResponseHeaders();
        $normalized = strtolower($filename);

        if (in_array($normalized, ['photo', 'photo.jpg', 'photo.jpeg', 'photo.png', 'photo.webp'], true)) {
            if (! $user->hasAnyClubPermission(self::STAFF_PHOTO_PERMISSIONS)) {
                abort(Response::HTTP_FORBIDDEN, 'Access denied. You do not have permission to view member photos.');
            }

            return $this->streamPrivateImage($member->person?->photo_path, $headers);
        }

        // Only authorized staff with KYC document access can view sensitive member documents
        if (! $user->hasClubPermission('members.kyc_view')) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied. You do not have permission to inspect member identification documents.');
        }

        // Resolve requested document
        $isIdProof = in_array($normalized, ['id_proof', 'id_proof.jpg', 'id_proof.png', 'id_proof.pdf', 'kyc'], true);
        $isApplication = in_array($normalized, ['application_form', 'application_form.pdf', 'application_form.jpg', 'application_form.png', 'application'], true);

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

    /**
     * Stream a dependent's verification portrait photo to authorized club staff.
     */
    public function showDependentPhoto(Request $request, Dependent $dependent)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required to access dependent records.');
        }

        if (! $user->hasAnyClubPermission(self::STAFF_PHOTO_PERMISSIONS)) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied. You do not have permission to view dependent photos.');
        }

        return $this->streamPrivateImage($dependent->person?->photo_path, $this->privateResponseHeaders());
    }

    /**
     * Stream the authenticated member's own portrait photo inside the Member Portal.
     */
    public function showPortalMemberPhoto()
    {
        $member = auth('member')->user();
        if (! $member instanceof Member) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $this->streamPrivateImage($member->person?->photo_path, $this->privateResponseHeaders());
    }

    /**
     * Stream a family dependent's portrait photo only to their owning primary member inside the Member Portal.
     */
    public function showPortalDependentPhoto(Dependent $dependent)
    {
        $member = auth('member')->user();
        if (! $member instanceof Member) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if ((int) $dependent->member_id !== (int) $member->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $this->streamPrivateImage($dependent->person?->photo_path, $this->privateResponseHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function privateResponseHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function streamPrivateImage(?string $path, array $headers)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->response($path, null, $headers);
    }
}
