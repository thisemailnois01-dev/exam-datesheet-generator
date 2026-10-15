<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
                <i class="fa-solid fa-clock-rotate-left text-blue-600 mr-2.5"></i> Version History & Audit Trail
            </h1>
            <p class="text-sm text-slate-500 mt-1">Review datesheet iterations, compare penalty metrics side-by-side, restore previous states, and audit change logs.</p>
        </div>
    </div>

    <!-- Versions Table & Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Version History List -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-code-branch text-blue-600 mr-2"></i> Saved Datesheet Iterations
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-700">
                                <th class="p-2.5 border border-slate-200">Version</th>
                                <th class="p-2.5 border border-slate-200">Name / Label</th>
                                <th class="p-2.5 border border-slate-200">Status</th>
                                <th class="p-2.5 border border-slate-200">Scheduled Exams</th>
                                <th class="p-2.5 border border-slate-200">Soft Score</th>
                                <th class="p-2.5 border border-slate-200">Created At</th>
                                <th class="p-2.5 border border-slate-200 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($versions as $ver)
                                <tr class="hover:bg-slate-50 border-b border-slate-100">
                                    <td class="p-2.5 border border-slate-200 font-extrabold text-blue-700">v{{ $ver->version }}</td>
                                    <td class="p-2.5 border border-slate-200 font-semibold text-slate-800">{{ $ver->name }}</td>
                                    <td class="p-2.5 border border-slate-200">
                                        <span class="px-2 py-0.5 rounded font-semibold text-[10px] {{ $ver->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ ucfirst($ver->status) }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 border border-slate-200">{{ $ver->exams->count() }} courses</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-amber-600">{{ $ver->soft_score }}</td>
                                    <td class="p-2.5 border border-slate-200 text-slate-400">{{ $ver->created_at->format('M d, H:i') }}</td>
                                    <td class="p-2.5 border border-slate-200 text-right space-x-1">
                                        <a href="{{ url('/export/pdf/' . $ver->id) }}" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded font-semibold text-slate-700">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                        <button wire:click="restoreVersion({{ $ver->id }})" 
                                                onclick="confirm('Restore this past version as the new active draft datesheet?') || event.stopImmediatePropagation()"
                                                class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded font-bold transition">
                                            <i class="fa-solid fa-rotate-left mr-1"></i> Restore
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-slate-400">No datesheet versions saved yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Version Comparison Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-code-compare text-indigo-600 mr-2"></i> Side-by-Side Version Comparison
                </h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Version A</label>
                        <select wire:model.live="compareVersion1Id" class="w-full text-xs bg-slate-50 border border-slate-300 rounded p-2">
                            <option value="">Choose Version...</option>
                            @foreach($versions as $v)
                                <option value="{{ $v->id }}">v{{ $v->version }} — {{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Version B</label>
                        <select wire:model.live="compareVersion2Id" class="w-full text-xs bg-slate-50 border border-slate-300 rounded p-2">
                            <option value="">Choose Version...</option>
                            @foreach($versions as $v)
                                <option value="{{ $v->id }}">v{{ $v->version }} — {{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($comp1 && $comp2)
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-slate-200">
                            <thead>
                                <tr class="bg-slate-100">
                                    <th class="p-2 border border-slate-200">Metric</th>
                                    <th class="p-2 border border-slate-200 text-blue-700">v{{ $comp1->version }} ({{ $comp1->name }})</th>
                                    <th class="p-2 border border-slate-200 text-indigo-700">v{{ $comp2->version }} ({{ $comp2->name }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-2 border border-slate-200 font-semibold">Scheduled Courses</td>
                                    <td class="p-2 border border-slate-200">{{ $comp1->exams->count() }}</td>
                                    <td class="p-2 border border-slate-200">{{ $comp2->exams->count() }}</td>
                                </tr>
                                <tr>
                                    <td class="p-2 border border-slate-200 font-semibold">Soft Penalty Score</td>
                                    <td class="p-2 border border-slate-200 font-bold {{ $comp1->soft_score <= $comp2->soft_score ? 'text-emerald-600' : 'text-slate-800' }}">{{ $comp1->soft_score }}</td>
                                    <td class="p-2 border border-slate-200 font-bold {{ $comp2->soft_score < $comp1->soft_score ? 'text-emerald-600' : 'text-slate-800' }}">{{ $comp2->soft_score }}</td>
                                </tr>
                                <tr>
                                    <td class="p-2 border border-slate-200 font-semibold">Creation Date</td>
                                    <td class="p-2 border border-slate-200 text-slate-500">{{ $comp1->created_at->toDayDateTimeString() }}</td>
                                    <td class="p-2 border border-slate-200 text-slate-500">{{ $comp2->created_at->toDayDateTimeString() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right Col: Spatie Activity Log / Audit Trail -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                <i class="fa-solid fa-list-check text-emerald-600 mr-2"></i> Audit Trail Activity Logs
            </h3>
            <p class="text-xs text-slate-500 mb-3">Immutable record of changes, timetable generations and restores.</p>

            <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                @forelse($activities as $act)
                    <div class="p-2.5 rounded bg-slate-50 border border-slate-200 text-xs">
                        <div class="flex items-center justify-between font-semibold text-slate-800">
                            <span class="text-blue-700 capitalize">{{ $act->event ?? 'Action' }}</span>
                            <span class="text-[10px] text-slate-400 font-normal">{{ $act->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-slate-600 mt-1">{{ $act->description }}</p>
                    </div>
                @empty
                    <div class="text-center py-6 text-slate-400 text-xs">No activity logged yet.</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
