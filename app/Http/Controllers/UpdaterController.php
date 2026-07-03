<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SystemUpdater;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdaterController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->hasPermission('menu.admin.settings'), 403, 'You do not have permission to access this menu.');

        $gitInstalled = SystemUpdater::isGitInstalled();
        $gitRepo = SystemUpdater::isGitRepo();
        $zipLoaded = SystemUpdater::isZipExtensionLoaded();
        $hasChanges = $gitInstalled && $gitRepo && SystemUpdater::hasUncommittedChanges();
        $currentCommit = $gitInstalled && $gitRepo ? SystemUpdater::getCurrentCommit() : null;

        $updateCheck = [
            'behind' => false,
            'message' => 'Cannot check for updates: Environment status checks failing.',
        ];

        if (!$hasChanges) {
            $updateCheck = SystemUpdater::checkForUpdates();
        }

        $currentVersion = config('updater.version', '1.0.0');
        $remoteVersion = SystemUpdater::getRemoteVersion() ?? 'Unknown';

        return view('admin.updater', [
            'gitInstalled' => $gitInstalled,
            'gitRepo' => $gitRepo,
            'zipLoaded' => $zipLoaded,
            'hasChanges' => $hasChanges,
            'currentCommit' => $currentCommit,
            'updateCheck' => $updateCheck,
            'currentVersion' => $currentVersion,
            'remoteVersion' => $remoteVersion,
        ]);
    }

    public function update(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('menu.admin.settings'), 403, 'You do not have permission to access this menu.');

        // If not Git repo, we need ZipArchive extension
        if (!SystemUpdater::isGitRepo() && !SystemUpdater::isZipExtensionLoaded()) {
            return response()->stream(function (): void {
                echo json_encode([
                    'step' => 'ERROR',
                    'message' => 'Cannot update: ZipArchive extension is not loaded in PHP and no Git repo is initialized.',
                ]) . "\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
            ]);
        }

        if (SystemUpdater::hasUncommittedChanges()) {
            return response()->stream(function (): void {
                echo json_encode([
                    'step' => 'ERROR',
                    'message' => 'Cannot update: You have uncommitted local changes that might cause conflicts.',
                ]) . "\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
            ]);
        }

        return response()->stream(function (): void {
            // Disable output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $logOutput = function (string $step, string $output): void {
                echo json_encode([
                    'step' => $step,
                    'message' => trim($output),
                ]) . "\n";
                if (ob_get_length()) {
                    ob_flush();
                }
                flush();
            };

            $success = SystemUpdater::runUpdateStreamed($logOutput);

            if ($success) {
                $logOutput('SUCCESS', 'Application updated successfully!');
            } else {
                $logOutput('ERROR', 'Update finished with failures.');
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
