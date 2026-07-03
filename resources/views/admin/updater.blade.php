@extends('layouts.app')

@section('title', 'System Updater')
@section('heading', 'Administration')
@section('subheading', 'Pull updates from GitHub and execute database migrations')

@section('content')
    @include('admin.partials.nav')

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Status and Actions -->
        <div class="space-y-6">
            <x-panel title="Software Version" subtitle="Version status">
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Current Version</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                            v{{ $currentVersion }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Latest Version</span>
                        @if ($remoteVersion === 'Unknown')
                            <span class="text-xs font-semibold text-slate-400">Unknown</span>
                        @elseif ($currentVersion !== $remoteVersion)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400">
                                v{{ $remoteVersion }} (Update Available)
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400">
                                v{{ $remoteVersion }} (Latest)
                            </span>
                        @endif
                    </div>
                </div>
            </x-panel>

            <x-panel title="System Checks" subtitle="Environment status checks">
                <div class="space-y-4">
                    <!-- Git Installed Check -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Git Installed</span>
                        @if ($gitInstalled)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <x-ming-icon name="system.check" class="h-4 w-4" /> Available
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                                <x-ming-icon name="system.close" class="h-4 w-4" /> Missing
                            </span>
                        @endif
                    </div>

                    <!-- Git Repo Check -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Git Repository</span>
                        @if ($gitRepo)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <x-ming-icon name="system.check" class="h-4 w-4" /> Valid
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                                <x-ming-icon name="system.close" class="h-4 w-4" /> Missing .git
                            </span>
                        @endif
                    </div>

                    <!-- Zip Extension Check -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Zip Extension (for fallbacks)</span>
                        @if ($zipLoaded)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <x-ming-icon name="system.check" class="h-4 w-4" /> Loaded
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                                <x-ming-icon name="system.close" class="h-4 w-4" /> Missing
                            </span>
                        @endif
                    </div>

                    <!-- Uncommitted Local Changes Check -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Local Changes</span>
                        @if ($hasChanges)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400">
                                <x-ming-icon name="system.warning" class="h-4 w-4" /> Uncommitted Files
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                <x-ming-icon name="system.check" class="h-4 w-4" /> None (Clean)
                            </span>
                        @endif
                    </div>
                </div>
            </x-panel>

            <x-panel title="Version Details" subtitle="Current commit details">
                @if ($currentCommit)
                    <div class="space-y-3 text-xs md:text-sm">
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Commit Hash</div>
                            <code class="font-mono bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-[11px] select-all break-all block">
                                {{ $currentCommit['hash'] }}
                            </code>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Message</div>
                            <div class="font-medium text-slate-800 dark:text-slate-200">{{ $currentCommit['message'] }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Author</div>
                            <div class="text-slate-700 dark:text-slate-300">{{ $currentCommit['author'] }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 font-semibold mb-0.5">Commit Date</div>
                            <div class="text-slate-700 dark:text-slate-300">{{ $currentCommit['date'] }}</div>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No Git commit history found.</p>
                @endif
            </x-panel>

            <x-panel title="Update Status" subtitle="Check remote repository">
                <div class="space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $updateCheck['message'] }}
                    </p>

                    @if ($updateCheck['behind'])
                        <div class="text-xs text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/30 p-3 rounded-xl space-y-2">
                            <div class="font-semibold flex items-center gap-1">
                                <x-ming-icon name="system.warning" class="h-4 w-4 text-rose-500" /> Database Backup Required
                            </div>
                            <p>Updating your application will modify system files and database schemas. Please ensure you have backed up your database to avoid any potential data loss.</p>
                            <label class="flex items-start gap-2 cursor-pointer mt-2">
                                <input type="checkbox" id="backup-confirm" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 mt-0.5">
                                <span class="font-medium text-slate-700 dark:text-slate-300 select-none">I have backed up my database.</span>
                            </label>
                        </div>

                        @if ($hasChanges)
                            <div class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/30 p-3 rounded-xl space-y-2">
                                <div class="font-semibold flex items-center gap-1">
                                    <x-ming-icon name="system.warning" class="h-4 w-4 text-amber-500" /> Local Modifications Detected
                                </div>
                                <p>You have uncommitted modifications or deleted system files (such as the deleted install folder or storage symlink). To update successfully, you must discard these changes.</p>
                                <label class="flex items-start gap-2 cursor-pointer mt-2">
                                    <input type="checkbox" id="force-confirm" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 mt-0.5">
                                    <span class="font-medium text-slate-700 dark:text-slate-300 select-none">Discard modifications and force update.</span>
                                </label>
                            </div>
                        @endif

                        <button type="button" id="install-update-btn" onclick="startUpdate()" class="w-full btn-primary flex items-center justify-center gap-2">
                            <x-ming-icon name="system.refresh-2" class="h-4 w-4 animate-spin-hover" />
                            Install Updates Now
                        </button>
                    @else
                        <button class="w-full btn-secondary cursor-not-allowed" disabled>
                            System Up To Date
                        </button>
                    @endif
                </div>
            </x-panel>
        </div>

        <!-- Terminal Logs Console -->
        <div class="lg:col-span-2">
            <x-panel title="Update Log Console" subtitle="Detailed output from updater runs">
                <div class="relative bg-slate-950 dark:bg-black border border-slate-800 rounded-xl overflow-hidden shadow-inner">
                    <!-- Terminal Header Bar -->
                    <div class="flex items-center justify-between bg-slate-900 border-b border-slate-800 px-4 py-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-rose-500"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                            <span class="ml-2 font-mono text-slate-500 select-none">updater-session.log</span>
                        </div>
                        <div class="font-mono text-slate-500 text-[10px] select-none">
                            UTF-8 | PHP-CLI
                        </div>
                    </div>

                    <!-- Log Content -->
                    <div class="p-4 font-mono text-xs text-slate-300 min-h-[360px] max-h-[500px] overflow-y-auto space-y-2 select-text" id="terminal-content">
                        <div class="text-slate-500 italic select-none">
                            // No log output generated in this browser session.<br>
                            // Confirm backup and click 'Install Updates Now' to fetch remote updates and view live installation logs.
                        </div>
                    </div>
                </div>
            </x-panel>
        </div>
    </div>

    <script>
        async function startUpdate() {
            const btn = document.getElementById('install-update-btn');
            const backupCheck = document.getElementById('backup-confirm');
            const forceCheck = document.getElementById('force-confirm');

            if (!backupCheck || !backupCheck.checked) {
                alert("Please confirm you have backed up your database before proceeding.");
                return;
            }

            if (forceCheck && !forceCheck.checked) {
                alert("Please check the box to discard local modifications to enable updating.");
                return;
            }

            if (!confirm("Are you sure you want to install updates now? The system will enter maintenance mode during this process.")) {
                return;
            }

            const force = forceCheck ? forceCheck.checked : false;

            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Installing...`;

            const consoleBox = document.getElementById('terminal-content');
            consoleBox.innerHTML = `<div class="text-sky-400 font-semibold animate-pulse">[UPDATER] Connecting to server update endpoint...</div>`;

            try {
                const response = await fetch("{{ route('admin.updater.run') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ force: force })
                });

                if (!response.ok) {
                    appendLogLine('ERROR', 'Failed to connect to the update handler endpoint.');
                    btn.disabled = false;
                    btn.innerHTML = `Install Updates Now`;
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                // Clear console box for real-time streaming logs
                consoleBox.innerHTML = '';

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split("\n");
                    
                    // Keep the last partial line in the buffer
                    buffer = lines.pop();

                    for (const line of lines) {
                        if (line.trim() === '') continue;
                        try {
                            const log = JSON.parse(line);
                            appendLogLine(log.step, log.message);
                        } catch (e) {
                            appendLogLine('RAW', line);
                        }
                    }
                }

                // Process remaining buffer
                if (buffer.trim() !== '') {
                    try {
                        const log = JSON.parse(buffer);
                        appendLogLine(log.step, log.message);
                    } catch (e) {
                        appendLogLine('RAW', buffer);
                    }
                }

            } catch (error) {
                appendLogLine('FATAL_ERROR', 'Update connection interrupted: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = `Install Updates Now`;
            }
        }

        function appendLogLine(step, message) {
            const consoleBox = document.getElementById('terminal-content');
            if (!consoleBox) return;

            // Remove empty placeholder comments
            const placeholder = consoleBox.querySelector('.italic');
            if (placeholder) {
                placeholder.remove();
            }

            let cssClass = 'text-slate-300';
            if (step === 'ERROR' || step === 'FATAL_ERROR') {
                cssClass = 'text-rose-500 font-bold';
            } else if (step === 'DOWNLOAD' || step === 'UPGRADE' || step === 'ZIP_FALLBACK' || step === 'GIT_PULL') {
                cssClass = 'text-sky-400 font-semibold';
            } else if (step === 'MAINTENANCE' || step === 'CACHE_CLEAR') {
                cssClass = 'text-amber-400';
            } else if (step === 'SUCCESS') {
                cssClass = 'text-emerald-500 font-bold';
                
                // On success, trigger page reload after delay
                setTimeout(() => {
                    window.location.reload();
                }, 4000);
            }

            const logRow = document.createElement('div');
            logRow.className = cssClass;
            logRow.textContent = `[${step}] ${message}`;
            consoleBox.appendChild(logRow);
            consoleBox.scrollTop = consoleBox.scrollHeight;
        }

        // Scroll terminal logs to bottom automatically
        document.addEventListener('DOMContentLoaded', () => {
            const consoleBox = document.getElementById('terminal-content');
            if (consoleBox) {
                consoleBox.scrollTop = consoleBox.scrollHeight;
            }
        });
    </script>

    <style>
        .animate-spin-hover:hover {
            animation: spin 1.5s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
@endsection
