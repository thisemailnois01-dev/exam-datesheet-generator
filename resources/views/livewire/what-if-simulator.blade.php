<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
            <i class="fa-solid fa-flask text-purple-600 mr-2.5"></i> What-If Simulation Sandbox
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Test "what-if" parameter modifications (e.g., 45-minute break instead of 30, fewer rooms, strict vs soft shifts) and immediately preview feasibility and soft penalty scores without overwriting or modifying saved datesheets.
        </p>
    </div>

    <!-- Parameter Controls Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Simulation Inputs -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center">
                <i class="fa-solid fa-sliders text-blue-600 mr-2"></i> Simulation Hypotheses
            </h3>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Simulated Exam Duration (Minutes)
                </label>
                <input type="number" wire:model="simDuration" min="30" max="180" step="15" 
                       class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                <span class="text-[11px] text-slate-400 mt-0.5 block">Baseline: {{ $baselineDuration }} min</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Simulated Mandatory Break B (Minutes)
                </label>
                <input type="number" wire:model="simBreak" min="0" max="120" step="5" 
                       class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                <span class="text-[11px] text-slate-400 mt-0.5 block">Baseline: {{ $baselineBreak }} min</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Simulated Available Rooms
                </label>
                <input type="number" wire:model="simRoomCount" min="1" max="50" 
                       class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                <span class="text-[11px] text-slate-400 mt-0.5 block">Baseline: {{ $baselineRoomCount }} rooms</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Shift Policy</label>
                <select wire:model="simShiftStrict" class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                    <option value="1">Strict (Hard)</option>
                    <option value="0">Preferred (Soft with Fallback)</option>
                </select>
            </div>

            <button wire:click="runSimulation" 
                    wire:loading.attr="disabled"
                    class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-sm rounded-lg shadow transition flex items-center justify-center">
                <i class="fa-solid fa-play mr-2" wire:loading.remove></i>
                <i class="fa-solid fa-spinner fa-spin mr-2" wire:loading></i>
                <span wire:loading.remove>Run In-Memory Simulation</span>
                <span wire:loading>Solving Scenario...</span>
            </button>
        </div>

        <!-- Simulation Results & Baseline Comparison -->
        <div class="lg:col-span-2 space-y-6">

            @if($hasRun)
                <!-- Outcome Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center justify-between">
                        <span>Simulation Outcome</span>
                        @if($simSuccess)
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                <i class="fa-solid fa-check mr-1"></i> Feasible Schedule Found
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                <i class="fa-solid fa-xmark mr-1"></i> Infeasible Scenario
                            </span>
                        @endif
                    </h3>

                    <!-- Comparison Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700">
                                    <th class="p-2.5 border border-slate-200">Evaluation Metric</th>
                                    <th class="p-2.5 border border-slate-200">Production Baseline</th>
                                    <th class="p-2.5 border border-slate-200">What-If Simulated</th>
                                    <th class="p-2.5 border border-slate-200">Variance / Impact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-2.5 border border-slate-200 font-semibold">Break Between Exams</td>
                                    <td class="p-2.5 border border-slate-200">{{ $baselineBreak }} mins</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-purple-700">{{ $simBreak }} mins</td>
                                    <td class="p-2.5 border border-slate-200">{{ $simBreak - $baselineBreak > 0 ? '+' . ($simBreak - $baselineBreak) : ($simBreak - $baselineBreak) }} mins</td>
                                </tr>
                                <tr class="bg-slate-50">
                                    <td class="p-2.5 border border-slate-200 font-semibold">Active Rooms</td>
                                    <td class="p-2.5 border border-slate-200">{{ $baselineRoomCount }} rooms</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-purple-700">{{ $simRoomCount }} rooms</td>
                                    <td class="p-2.5 border border-slate-200">{{ $simRoomCount - $baselineRoomCount }} rooms</td>
                                </tr>
                                <tr>
                                    <td class="p-2.5 border border-slate-200 font-semibold">Total Generated Slots</td>
                                    <td class="p-2.5 border border-slate-200">Standard Grid</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-blue-700">{{ $simSlotsCount }} slots</td>
                                    <td class="p-2.5 border border-slate-200">—</td>
                                </tr>
                                <tr class="bg-slate-50">
                                    <td class="p-2.5 border border-slate-200 font-semibold">Soft Constraint Penalty</td>
                                    <td class="p-2.5 border border-slate-200">{{ $baselineSoftScore ?? 'N/A' }}</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-amber-700">{{ $simSuccess ? $simSoftScore : 'N/A' }}</td>
                                    <td class="p-2.5 border border-slate-200">
                                        @if($baselineSoftScore !== null && $simSuccess)
                                            {{ $simSoftScore <= $baselineSoftScore ? 'Improved (Lower penalty)' : 'Degraded (Higher penalty)' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="p-2.5 border border-slate-200 font-semibold">Execution Time</td>
                                    <td class="p-2.5 border border-slate-200">< 0.05s</td>
                                    <td class="p-2.5 border border-slate-200 font-bold text-emerald-700">{{ sprintf('%.4f s', $simTime) }}</td>
                                    <td class="p-2.5 border border-slate-200 text-emerald-600 font-semibold">Instant (< 1s)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if(!$simSuccess)
                        <div class="mt-4 p-4 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                            <strong>Bottleneck Explanation:</strong>
                            <p class="mt-1 whitespace-pre-line">{{ $simExplanation }}</p>

                            @if(!empty($simSuggestions))
                                <div class="mt-2 pt-2 border-t border-rose-200">
                                    <strong>Suggestions:</strong>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                                        @foreach($simSuggestions as $sug)
                                            <li>{{ $sug }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-400">
                    <i class="fa-solid fa-microscope text-slate-300 text-5xl mb-3"></i>
                    <h4 class="text-base font-bold text-slate-700">No Simulation Executed Yet</h4>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Adjust the hypothesis values on the left and click "Run In-Memory Simulation" to analyze the scheduling consequences.
                    </p>
                </div>
            @endif

        </div>

    </div>

</div>
