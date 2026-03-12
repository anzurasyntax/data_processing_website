<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Data Overview</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 min-h-screen text-white">

<div class="max-w-6xl mx-auto px-4 py-6 md:py-10">
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Welcome back</h1>
            <p class="text-sm md:text-base text-slate-300 mt-1">
                Quick snapshot of your datasets, quality, and recent activity.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('files.upload') }}"
               class="inline-flex items-center justify-center rounded-lg bg-blue-500 hover:bg-blue-600 px-4 py-2 text-sm font-semibold shadow-lg shadow-blue-500/30 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v16m8-8H4"/>
                </svg>
                Upload new file
            </a>
            <a href="{{ route('files.list') }}"
               class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800/70 transition">
                My files
            </a>
        </div>
    </header>

    {{-- Summary cards --}}
    <section class="grid gap-4 md:gap-5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total files</span>
                <span class="px-2 py-1 rounded-full bg-slate-800 text-[11px] text-slate-300">All time</span>
            </div>
            <p class="mt-3 text-3xl font-semibold">
                {{ number_format($totalFiles) }}
            </p>
        </div>

        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total size</span>
                <span class="px-2 py-1 rounded-full bg-slate-800 text-[11px] text-slate-300">Stored</span>
            </div>
            @php
                $kb = $totalSizeBytes / 1024;
                $sizeLabel = $kb < 1024
                    ? number_format($kb, 1) . ' KB'
                    : number_format($kb / 1024, 2) . ' MB';
            @endphp
            <p class="mt-3 text-2xl font-semibold">
                {{ $sizeLabel }}
            </p>
        </div>

        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Average quality</span>
                <span class="px-2 py-1 rounded-full bg-slate-800 text-[11px] text-slate-300">Last 5 files</span>
            </div>
            @if(!is_null($averageQuality))
                @php
                    $color = $averageQuality >= 80 ? 'text-emerald-400' : ($averageQuality >= 60 ? 'text-amber-300' : 'text-rose-300');
                @endphp
                <p class="mt-3 text-3xl font-semibold {{ $color }}">
                    {{ $averageQuality }}%
                </p>
            @else
                <p class="mt-3 text-sm text-slate-400">
                    No quality scores yet. Upload a file to run a quality check.
                </p>
            @endif
        </div>

        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Activity</span>
                <span class="px-2 py-1 rounded-full bg-slate-800 text-[11px] text-slate-300">Recent</span>
            </div>
            <p class="mt-3 text-sm text-slate-300">
                {{ $recentFiles->count() > 0 ? 'You have been working with ' . $recentFiles->count() . ' recent file(s).' : 'No recent activity yet.' }}
            </p>
        </div>
    </section>

    {{-- Processing status summary --}}
    <section class="grid gap-4 md:gap-5 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending</div>
            <div class="mt-2 text-2xl font-semibold text-slate-100">{{ number_format($processingCounts['pending'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Processing</div>
            <div class="mt-2 text-2xl font-semibold text-blue-200">{{ number_format($processingCounts['processing'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Completed</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-200">{{ number_format($processingCounts['completed'] ?? 0) }}</div>
        </div>
        <div class="rounded-2xl bg-slate-900/60 border border-slate-700/80 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Failed</div>
            <div class="mt-2 text-2xl font-semibold text-rose-200">{{ number_format($processingCounts['failed'] ?? 0) }}</div>
        </div>
    </section>

    {{-- Recent files & activity --}}
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-2xl bg-slate-900/70 border border-slate-700/80 p-4 md:p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base md:text-lg font-semibold">Recent files</h2>
                <a href="{{ route('files.list') }}" class="text-xs md:text-sm text-blue-300 hover:text-blue-200 underline">
                    View all
                </a>
            </div>

            @if($recentFiles->isEmpty())
                <p class="text-sm text-slate-400 py-4">
                    You haven't uploaded any files yet. Start by uploading your first dataset.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs md:text-sm min-w-max">
                        <thead>
                        <tr class="text-slate-400 border-b border-slate-700">
                            <th class="py-2 pr-3 text-left font-medium">File</th>
                            <th class="py-2 px-3 text-left font-medium hidden sm:table-cell">Type</th>
                            <th class="py-2 px-3 text-left font-medium">Quality</th>
                            <th class="py-2 px-3 text-left font-medium hidden md:table-cell">Uploaded</th>
                            <th class="py-2 pl-3 text-right font-medium">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($recentFiles as $file)
                            @php
                                $summary = $qualitySummaries[$file->id] ?? ['score' => null, 'is_clean' => null];
                                $score = $summary['score'];
                                $isClean = $summary['is_clean'];
                            @endphp
                            <tr class="border-b border-slate-800/80 hover:bg-slate-800/60">
                                <td class="py-2 pr-3 align-middle">
                                    <a href="{{ route('files.preview', $file->slug) }}"
                                       class="block text-xs md:text-sm font-semibold text-slate-50 hover:text-blue-200 truncate max-w-[160px] md:max-w-none">
                                        {{ $file->original_name }}
                                    </a>
                                    <p class="text-[11px] text-slate-400 mt-0.5 md:hidden">
                                        {{ $file->created_at->diffForHumans() }}
                                    </p>
                                </td>
                                <td class="py-2 px-3 align-middle hidden sm:table-cell whitespace-nowrap text-slate-300">
                                    {{ strtoupper($file->file_type) }}
                                </td>
                                <td class="py-2 px-3 align-middle">
                                    @if(!is_null($score))
                                        @php
                                            $badgeColor = $score >= 80 ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/50'
                                                : ($score >= 60 ? 'bg-amber-500/15 text-amber-200 border-amber-500/50'
                                                : 'bg-rose-500/15 text-rose-200 border-rose-500/50');
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium {{ $badgeColor }}">
                                            {{ $score }}%
                                            @if(!is_null($isClean))
                                                <span class="ml-1 text-[10px] opacity-80">
                                                    {{ $isClean ? 'Clean' : 'Needs work' }}
                                                </span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400">
                                            Not yet checked
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 align-middle text-right text-[11px] md:text-xs text-slate-400 hidden md:table-cell whitespace-nowrap">
                                    {{ $file->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="py-2 pl-3 align-middle text-right whitespace-nowrap">
                                    <a href="{{ route('files.preview', $file->slug) }}" class="text-blue-300 hover:text-blue-200 text-xs font-medium mr-2">Preview</a>
                                    <a href="{{ route('files.visualize', $file->slug) }}" class="text-emerald-300 hover:text-emerald-200 text-xs font-medium mr-2">Visualize</a>
                                    <a href="{{ route('files.insight-strategy', $file->slug) }}" class="text-amber-300 hover:text-amber-200 text-xs font-medium">Insight & Strategy</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-slate-900/70 border border-slate-700/80 p-4 md:p-5">
            <h2 class="text-base md:text-lg font-semibold mb-3">Data cleaning tools</h2>
            <p class="text-xs text-slate-400 mb-3">Available in Preview → Show Cleaning Tools</p>
            <ul class="space-y-2 text-xs text-slate-200">
                <li><span class="text-red-400 font-medium">Handle missing values</span> — Mean, median, mode, forward/backward fill, interpolate, constant, remove rows/column</li>
                <li><span class="text-purple-400 font-medium">Handle outliers</span> — Remove, cap at IQR, winsorize, log/sqrt transform</li>
                <li><span class="text-orange-400 font-medium">Remove duplicates</span> — All columns or selected columns, keep first/last</li>
                <li><span class="text-green-400 font-medium">Normalize column</span> — Min-max, z-score, robust (median & MAD)</li>
                <li><span class="text-indigo-400 font-medium">Bulk operations</span> — Remove empty rows/columns, auto-impute all missing</li>
            </ul>
            <a href="{{ route('files.list') }}" class="mt-3 inline-block text-xs text-blue-300 hover:text-blue-200 underline">Open a file to use tools →</a>
        </div>

        <div class="rounded-2xl bg-slate-900/70 border border-slate-700/80 p-4 md:p-5">
            <h2 class="text-base md:text-lg font-semibold mb-3">Next steps</h2>
            <ul class="space-y-3 text-sm text-slate-200">
                <li class="flex items-start gap-2">
                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    <div>
                        <span class="font-semibold">Run a quality report</span>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Open a recent file’s quality page to understand missing values, duplicates, and outliers.
                        </p>
                    </div>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                    <div>
                        <span class="font-semibold">Clean your data</span>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Use the preview screen’s advanced tools panel to impute, normalize, and remove duplicates.
                        </p>
                    </div>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1 h-1.5 w-1.5 rounded-full bg-amber-300"></span>
                    <div>
                        <span class="font-semibold">Re-check quality</span>
                        <p class="text-xs text-slate-400 mt-0.5">
                            After cleaning, re-run the quality report to see how your score improves over time.
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</div>

</body>
</html>

