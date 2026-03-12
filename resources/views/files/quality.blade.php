<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Quality Report - {{ $file->original_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Data Quality Report</h1>
                <p class="text-gray-600">{{ $file->original_name }} ({{ strtoupper($file->file_type) }})</p>
            </div>
            <a href="{{ route('files.list') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                Back to Files
            </a>
        </div>
    </div>

    <!-- Error Message -->
    @if(isset($error))
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>{{ $error }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!isset($qualityResult) || $qualityResult === null)
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="text-center py-8">
            @if(in_array($file->processing_status, ['pending', 'processing']))
                <p class="text-gray-600 mb-2 font-medium">Dataset is currently being analyzed...</p>
                <p class="text-gray-500 text-sm mb-4">This page will refresh automatically when processing completes.</p>
                <div class="text-xs text-gray-400" id="processing-status-label">
                    Status: {{ $file->processing_status }}
                </div>
            @elseif($file->processing_status === 'failed')
                <p class="text-gray-600 mb-4">Processing failed. Please try again.</p>
                <a href="{{ route('files.quality', $file->slug) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Retry Quality Check
                </a>
            @else
                <p class="text-gray-600 mb-4">Unable to load quality report. Please try again.</p>
                <a href="{{ route('files.quality', $file->slug) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Retry Quality Check
                </a>
            @endif
        </div>
    </div>

    @if(in_array($file->processing_status, ['pending', 'processing']))
    <script>
      (function () {
        const statusUrl = @json(route('files.status', $file->slug));
        const label = document.getElementById('processing-status-label');

        async function poll() {
          try {
            const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const status = json?.data?.processing_status;
            if (label && status) label.textContent = 'Status: ' + status;
            if (status === 'completed') {
              window.location.reload();
              return;
            }
            if (status === 'failed') {
              window.location.reload();
              return;
            }
          } catch (e) {
            // Ignore transient polling failures
          }
          setTimeout(poll, 3000);
        }

        setTimeout(poll, 1000);
      })();
    </script>
    @endif
    @else

    <!-- Quality Score Card -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-semibold text-gray-800">Overall Quality Score</h2>
            @if($qualityResult['is_clean'])
                <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full font-semibold">
                    ✓ Clean Data
                </span>
            @else
                <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full font-semibold">
                    ⚠ Needs Cleaning
                </span>
            @endif
        </div>
        
        <div class="relative">
            <div class="w-full bg-gray-200 rounded-full h-8 mb-4">
                <div class="h-8 rounded-full flex items-center justify-center text-white font-bold transition-all duration-500
                    {{ $qualityResult['quality_score'] >= 80 ? 'bg-green-500' : ($qualityResult['quality_score'] >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}"
                    style="width: {{ $qualityResult['quality_score'] }}%">
                    {{ $qualityResult['quality_score'] }}%
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-sm text-blue-600 font-semibold mb-1">Total Rows</div>
                <div class="text-2xl font-bold text-blue-800">{{ number_format($qualityResult['total_rows']) }}</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-sm text-purple-600 font-semibold mb-1">Total Columns</div>
                <div class="text-2xl font-bold text-purple-800">{{ $qualityResult['total_columns'] }}</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <div class="text-sm text-red-600 font-semibold mb-1">Missing Values</div>
                <div class="text-2xl font-bold text-red-800">{{ number_format($qualityResult['total_missing']) }}</div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg">
                <div class="text-sm text-orange-600 font-semibold mb-1">Duplicate Rows</div>
                <div class="text-2xl font-bold text-orange-800">{{ number_format($qualityResult['total_duplicate_rows']) }}</div>
            </div>
        </div>
    </div>

    <!-- Issues Summary -->
    @if(count($qualityResult['issues']) > 0)
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Data Quality Issues</h2>
        <div class="space-y-3">
            @foreach($qualityResult['issues'] as $issue)
                <div class="flex items-start p-4 rounded-lg border-l-4
                    {{ $issue['severity'] === 'high' ? 'bg-red-50 border-red-500' : ($issue['severity'] === 'medium' ? 'bg-yellow-50 border-yellow-500' : 'bg-blue-50 border-blue-500') }}">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800 mb-1">
                            {{ ucfirst(str_replace('_', ' ', $issue['type'])) }}
                        </div>
                        <div class="text-gray-600">{{ $issue['message'] }}</div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        {{ $issue['severity'] === 'high' ? 'bg-red-200 text-red-800' : ($issue['severity'] === 'medium' ? 'bg-yellow-200 text-yellow-800' : 'bg-blue-200 text-blue-800') }}">
                        {{ ucfirst($issue['severity']) }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Column Quality Details -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Column Quality Details</h2>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-3 text-left font-semibold text-gray-700">Column</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Data Type</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Missing</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Duplicates</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Outliers</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Unique Values</th>
                        <th class="border px-4 py-3 text-center font-semibold text-gray-700">Issues</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($qualityResult['column_quality'] as $column => $quality)
                        <tr class="hover:bg-gray-50">
                            <td class="border px-4 py-3 font-medium text-gray-800">{{ $column }}</td>
                            <td class="border px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    {{ $quality['data_type'] === 'number' ? 'bg-blue-100 text-blue-800' : ($quality['data_type'] === 'text' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800') }}">
                                    {{ ucfirst(str_replace('-', ' ', $quality['data_type'])) }}
                                </span>
                            </td>
                            <td class="border px-4 py-3 text-center">
                                @if($quality['missing_count'] > 0)
                                    <span class="text-red-600 font-semibold">{{ $quality['missing_count'] }}</span>
                                    <span class="text-gray-500 text-sm">({{ $quality['missing_percentage'] }}%)</span>
                                @else
                                    <span class="text-green-600 font-semibold">0</span>
                                @endif
                            </td>
                            <td class="border px-4 py-3 text-center">
                                @if($quality['duplicate_count'] > 0)
                                    <span class="text-orange-600 font-semibold">{{ $quality['duplicate_count'] }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="border px-4 py-3 text-center">
                                @if($quality['outlier_count'] > 0)
                                    <span class="text-purple-600 font-semibold">{{ $quality['outlier_count'] }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="border px-4 py-3 text-center text-gray-700">{{ number_format($quality['unique_values']) }}</td>
                            <td class="border px-4 py-3 text-center">
                                @if(count($quality['issues']) > 0)
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-semibold">
                                        {{ count($quality['issues']) }} issue(s)
                                    </span>
                                @else
                                    <span class="text-green-600 font-semibold">✓ Clean</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Ready to Clean Your Data?</h3>
                <p class="text-gray-600">Use our advanced cleaning tools to fix all data quality issues in real-time.</p>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('files.preview', $file->slug) }}" class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-semibold">
                    View Data
                </a>
                @if(!$qualityResult['is_clean'])
                    <a href="{{ route('files.preview', $file->slug) }}?clean=true" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold shadow-lg">
                        🧹 Clean the Data
                    </a>
                @endif
            </div>
        </div>
    </div>

    @endif

</div>

</body>
</html>
