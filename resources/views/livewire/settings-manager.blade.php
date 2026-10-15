<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">System Settings & Timing Model</h1>
            <p class="text-sm text-slate-500 mt-1">Configure global exam duration, transition breaks, daily shift windows, rooms, and constraint weights.</p>
        </div>
        <div class="flex items-center space-x-3">
            <div class="px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold flex items-center">
                <i class="fa-solid fa-clock-rotate-left mr-1.5 text-blue-600"></i> Projected Slots: {{ $projectedSlotsCount }}
            </div>
            <button wire:click="saveSettings" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg shadow transition">
                <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Settings
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1: Core Timing Model & Calendar -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Timing Model (Duration D & Break B) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-stopwatch text-blue-600 mr-2"></i> Timing Model Rules (Central Engine)
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    Slots are NOT a fixed list. They are generated from: slot<sub>1</sub> starts at window_start; slot<sub>n+1</sub> starts at slot<sub>n</sub>.end + B; end &le; window_end.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Exam Duration D (Minutes)</label>
                        <input type="number" wire:model.live.debounce.400ms="defaultDurationMinutes" min="30" max="240" step="15"
                               class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Standard demo default: 60 minutes</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mandatory Break B (Minutes)</label>
                        <input type="number" wire:model.live.debounce.400ms="breakMinutes" min="0" max="120" step="5"
                               class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Standard demo default: 30 minutes</span>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Shift Enforcement Policy</label>
                        <div class="flex items-center space-x-4 mt-1">
                            <label class="inline-flex items-center text-xs font-medium text-slate-700">
                                <input type="radio" wire:model.live="shiftRuleStrict" value="1" class="text-blue-600">
                                <span class="ml-2 font-bold text-slate-800">STRICT (Hard Constraint)</span> — Morning only in Morning, Evening only in Evening
                            </label>
                            <label class="inline-flex items-center text-xs font-medium text-slate-700">
                                <input type="radio" wire:model.live="shiftRuleStrict" value="0" class="text-blue-600">
                                <span class="ml-2 font-bold text-slate-800">PREFERRED (Soft Fallback)</span> — Allows cross-shift with penalty if infeasible
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exam Calendar & Working Days -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-calendar-days text-indigo-600 mr-2"></i> Examination Calendar & Working Weekdays
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Session Start Date</label>
                        <input type="date" wire:model.live="sessionStartDate" class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[11px] text-slate-500 mt-1 block">Exams begin on this date for all batches</span>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Session Closing Date</label>
                        <div class="w-full text-xs bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 text-slate-700 font-semibold flex items-center justify-between">
                            <span>Dynamic (Determined by Courses)</span>
                            <span class="text-blue-600"><i class="fa-solid fa-calculator mr-1"></i> Auto</span>
                        </div>
                        <span class="text-[11px] text-slate-500 mt-1 block">Each batch automatically finishes when its courses are exhausted (e.g. 4–7 working days)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">Active Working Weekdays</label>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day)
                            <label class="flex items-center space-x-1.5 p-2 rounded bg-slate-50 border border-slate-200 text-xs font-medium cursor-pointer">
                                <input type="checkbox" wire:model.live="workingDays" value="{{ $day }}" class="text-blue-600 rounded">
                                <span>{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Shifts Manager -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-business-time text-emerald-600 mr-2"></i> Daily Shift Windows
                    </h3>
                </div>

                <div class="space-y-2 mb-4">
                    @foreach($shifts as $sh)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 border border-slate-200">
                            <div class="flex items-center space-x-3">
                                <button wire:click="toggleShift({{ $sh->id }})" class="w-6 h-6 rounded-full flex items-center justify-center {{ $sh->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-400' }}">
                                    <i class="fa-solid {{ $sh->is_active ? 'fa-check' : 'fa-xmark' }} text-xs"></i>
                                </button>
                                <div>
                                    <span class="font-bold text-sm text-slate-900">{{ $sh->name }}</span>
                                    <span class="text-xs text-slate-500 ml-2">{{ $sh->start_time }} to {{ $sh->end_time }}</span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs px-2 py-0.5 rounded font-semibold {{ $sh->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $sh->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <button wire:click="deleteShift({{ $sh->id }})" 
                                        wire:confirm="Are you sure you want to delete this daily shift window?" 
                                        title="Delete Shift Window"
                                        class="text-rose-500 hover:text-rose-700 p-1.5 rounded hover:bg-rose-50 transition">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Add New Shift -->
                <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">Shift Name</label>
                        <input type="text" wire:model="newShiftName" placeholder="e.g. Afternoon" class="w-full text-xs bg-slate-50 border border-slate-300 rounded px-2.5 py-1.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">Start Time</label>
                        <input type="time" wire:model="newShiftStart" class="w-full text-xs bg-slate-50 border border-slate-300 rounded px-2.5 py-1.5">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">End Time</label>
                        <input type="time" wire:model="newShiftEnd" class="w-full text-xs bg-slate-50 border border-slate-300 rounded px-2.5 py-1.5">
                    </div>
                    <div>
                        <button wire:click="addShift" class="w-full text-xs font-bold bg-slate-800 hover:bg-slate-900 text-white rounded px-3 py-2 transition">
                            + Add Shift
                        </button>
                    </div>
                </div>
            </div>

            <!-- Institute Branding & Official Document Format -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-2 flex items-center">
                    <i class="fa-solid fa-building-columns text-indigo-600 mr-2"></i> Universal Institute Branding & Official Header Metadata
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    Customize your institution details. These settings dynamically populate the official printed PDF datesheets, headers, and reference blocks for any university or department.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">University / Institution Full Name</label>
                        <input type="text" wire:model="universityName" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. Baba Guru Nanak University, NUST, etc.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Department / Faculty Name</label>
                        <input type="text" wire:model="departmentName" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. Department of Computer Science & IT</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Examination Document Title</label>
                        <input type="text" wire:model="examTitle" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. DATE SHEET FOR FINAL TERM EXAMINATION</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Academic Session / Term</label>
                        <input type="text" wire:model="sessionTerm" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. SPRING 2026 or FALL 2026</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Official Reference Number</label>
                        <input type="text" wire:model="refNo" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. BGNU/CS&IT/2026/46</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Official Issue / Notification Date</label>
                        <input type="text" wire:model="issueDate" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. 03.06.2026</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Chairperson / Controller Name</label>
                        <input type="text" wire:model="chairpersonName" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Designation / Signatory Title</label>
                        <input type="text" wire:model="chairpersonTitle" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">e.g. Chairperson, Controller of Examinations</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Left Crest / Department Logo Path</label>
                        <input type="text" wire:model="leftLogo" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Default: images/dept_logo.png</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Right Crest / University Logo Path</label>
                        <input type="text" wire:model="rightLogo" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Default: images/bgnu_logo.png</span>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Official CC Distribution List (One entry per line)</label>
                        <textarea wire:model="ccList" rows="4" class="w-full text-xs font-mono bg-slate-50 border border-slate-300 rounded-lg p-2.5"></textarea>
                    </div>
                </div>
            </div>

        </div>

        <!-- Column 2: Rooms & Constraint Weights -->
        <div class="space-y-6">

            <!-- Examination Rooms -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center">
                        <i class="fa-solid fa-door-open text-amber-600 mr-2"></i> Examination Rooms
                    </h3>
                    <span class="text-xs font-semibold text-slate-500">{{ $rooms->count() }} Configured</span>
                </div>

                <div class="max-h-56 overflow-y-auto space-y-1.5 mb-4 pr-1">
                    @foreach($rooms as $rm)
                        <div class="flex items-center justify-between p-2 rounded bg-slate-50 border border-slate-200 text-xs">
                            <div class="flex items-center space-x-2">
                                <button wire:click="toggleRoom({{ $rm->id }})" class="w-5 h-5 rounded flex items-center justify-center {{ $rm->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-400' }}">
                                    <i class="fa-solid {{ $rm->is_active ? 'fa-check' : 'fa-xmark' }} text-[10px]"></i>
                                </button>
                                <span class="font-bold text-slate-800">{{ $rm->name }}</span>
                                <span class="text-slate-400">({{ $rm->capacity }} seats)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-[10px] uppercase font-semibold text-slate-500">{{ $rm->type }}</span>
                                <button wire:click="deleteRoom({{ $rm->id }})" 
                                        wire:confirm="Are you sure you want to delete this examination room?" 
                                        title="Delete Room"
                                        class="text-rose-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50 transition">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Add Room Form -->
                <div class="pt-3 border-t border-slate-100 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">Room Name</label>
                            <input type="text" wire:model="newRoomName" placeholder="Room 105" class="w-full text-xs bg-slate-50 border border-slate-300 rounded px-2.5 py-1.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-0.5">Capacity</label>
                            <input type="number" wire:model="newRoomCapacity" class="w-full text-xs bg-slate-50 border border-slate-300 rounded px-2.5 py-1.5">
                        </div>
                    </div>
                    <button wire:click="addRoom" class="w-full text-xs font-bold bg-slate-800 hover:bg-slate-900 text-white rounded px-3 py-1.5 transition">
                        + Add Examination Room
                    </button>
                </div>
            </div>

            <!-- Soft Constraint Penalty Weights -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center">
                    <i class="fa-solid fa-scale-balanced text-purple-600 mr-2"></i> Soft Constraint Penalty Weights
                </h3>
                <p class="text-xs text-slate-500 mb-3">
                    Adjust solver optimization priorities. Higher penalty weights are penalized more aggressively by the Simulated Annealing engine.
                </p>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>Consecutive Exam Days Penalty</span>
                            <span>{{ $weights['consecutive_exam_penalty'] ?? 50 }}</span>
                        </div>
                        <input type="range" wire:model="weights.consecutive_exam_penalty" min="0" max="200" step="5" class="w-full">
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>Same-Day Multiple Exams Penalty</span>
                            <span>{{ $weights['same_day_exam_penalty'] ?? 100 }}</span>
                        </div>
                        <input type="range" wire:model="weights.same_day_exam_penalty" min="0" max="300" step="10" class="w-full">
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>Core Course Late Penalty</span>
                            <span>{{ $weights['core_course_late_penalty'] ?? 20 }}</span>
                        </div>
                        <input type="range" wire:model="weights.core_course_late_penalty" min="0" max="100" step="5" class="w-full">
                    </div>

                    <div>
                        <div class="flex justify-between text-xs font-semibold text-slate-700 mb-1">
                            <span>Soft Shift Violation Penalty</span>
                            <span>{{ $weights['soft_shift_violation'] ?? 80 }}</span>
                        </div>
                        <input type="range" wire:model="weights.soft_shift_violation" min="0" max="200" step="5" class="w-full">
                    </div>
                </div>
            </div>

            <!-- Workspace Data Lifecycle & Onboarding Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center">
                    <i class="fa-solid fa-arrows-rotate text-rose-600 mr-2"></i> Data Lifecycle & Reset Actions
                </h3>
                <p class="text-xs text-slate-500">
                    Handing this software over to a new user or department? Reset to an empty clean slate or reload the benchmark departmental demo data.
                </p>

                <div class="space-y-2.5 pt-2">
                    <button wire:click="resetToCleanSlate" 
                            wire:confirm="CAUTION: This will delete all existing datesheets, courses, and batches so you can start completely fresh. Are you sure you want to reset?" 
                            class="w-full py-2.5 px-3 rounded-lg border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center justify-center">
                        <i class="fa-solid fa-trash-can mr-2 text-rose-600"></i> Reset Workspace to Clean Slate (Zero Data)
                    </button>

                    <button wire:click="loadDemoData" 
                            wire:confirm="This will load the verified departmental curriculum (24 batches, 130 courses, 28 teachers) with 0 hard conflicts. Proceed?" 
                            class="w-full py-2.5 px-3 rounded-lg border border-indigo-300 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition flex items-center justify-center">
                        <i class="fa-solid fa-database mr-2 text-indigo-600"></i> Reload Official BGNU Demo Benchmark
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>
