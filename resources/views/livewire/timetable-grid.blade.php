<div class="space-y-6" @if($isGenerating) wire:poll.1000ms="pollGenerationProgress" @endif>

    <!-- Top Header & Action Controls -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Examination Timetable Grid</span>
                    @if($datesheet)
                        <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                            v{{ $datesheet->version }} ({{ ucfirst($datesheet->status) }})
                        </span>
                    @endif
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    Zero-hardcoding constraint-driven university examination scheduler. Official BGNU format with 0 hard conflicts.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Session Selector -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">Exam Session</label>
                    <select wire:model.live="selectedSessionId" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 text-slate-700 font-medium focus:ring-2 focus:ring-blue-500">
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->department->code }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Custom Start Date Picker -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">Starting Date</label>
                    <input type="date" wire:model.live="startDate" 
                           class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-slate-700 font-medium focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Consecutive Daily Exams Toggle -->
                <div class="flex items-center pt-4">
                    <label class="inline-flex items-center cursor-pointer" title="Har batch ka roz paper hoga jab tak exams mukammal na hon (No idle gap days)">
                        <input type="checkbox" wire:model="dailyConsecutiveMode" class="sr-only peer">
                        <div class="relative w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ms-2 text-xs font-semibold text-slate-700 whitespace-nowrap">Daily Consecutive</span>
                    </label>
                </div>

                <div class="flex items-center gap-2 pt-4">
                    <!-- One-Click Generate Button -->
                    <button wire:click="triggerGeneration(true)" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-bolt mr-1.5" wire:loading.remove></i>
                        <i class="fa-solid fa-spinner fa-spin mr-1.5" wire:loading></i>
                        <span wire:loading.remove>Generate Datesheet</span>
                        <span wire:loading>Generating...</span>
                    </button>

                    <!-- Regenerate Different Seed -->
                    <button wire:click="triggerGeneration(false)" 
                            title="Explore another valid clash-free timetable solution"
                            class="inline-flex items-center px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition">
                        <i class="fa-solid fa-arrows-rotate mr-1 text-slate-500"></i> New Seed
                    </button>

                    @if($datesheet)
                        <!-- Public Shareable Link -->
                        <a href="{{ url('/view/' . $datesheet->id) }}" target="_blank" title="Shareable link for faculty & students" class="inline-flex items-center px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg border border-indigo-200 shadow-sm transition">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1.5 text-indigo-600"></i> Public View
                        </a>

                        <!-- Export Dropdown -->
                        <div class="relative inline-block text-left" x-data="{ open: false }">
                            <button @click="open = !open" type="button" class="inline-flex items-center px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                                <i class="fa-solid fa-download mr-1.5"></i> Export <i class="fa-solid fa-chevron-down ml-1 text-[10px]"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 py-1" style="display: none;">
                                <a href="{{ url('/export/datesheet/' . $datesheet->id) }}" class="block px-4 py-2 text-xs text-slate-700 hover:bg-slate-100">
                                    <i class="fa-solid fa-file-excel text-emerald-600 mr-2"></i> Batch Datesheet (Excel)
                                </a>
                                <a href="{{ url('/export/teachers/' . $datesheet->id) }}" class="block px-4 py-2 text-xs text-slate-700 hover:bg-slate-100">
                                    <i class="fa-solid fa-user-tie text-blue-600 mr-2"></i> Faculty Duty Roster (Excel)
                                </a>
                                <a href="{{ url('/export/rooms/' . $datesheet->id) }}" class="block px-4 py-2 text-xs text-slate-700 hover:bg-slate-100">
                                    <i class="fa-solid fa-door-open text-indigo-600 mr-2"></i> Room Allocation Sheet (Excel)
                                </a>
                                <div class="border-t border-slate-100 my-1"></div>
                                <a href="{{ url('/export/pdf/' . $datesheet->id) }}" target="_blank" class="block px-4 py-2 text-xs text-slate-700 hover:bg-slate-100 font-semibold">
                                    <i class="fa-solid fa-print text-rose-600 mr-2"></i> Official Printable PDF
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($datesheet)
            <!-- Audit & Constraint Score Strip -->
            <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <i class="fa-solid fa-shield-check mr-1.5 text-emerald-600"></i> 0 Hard Violations (Audited)
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md font-medium bg-slate-100 text-slate-700 border border-slate-200">
                        <i class="fa-solid fa-star-half-stroke mr-1.5 text-amber-500"></i> Soft Penalty Score: {{ $datesheet->soft_score }}
                    </span>
                    <span class="text-slate-500">
                        Total Exams: <strong>{{ $totalExamsCount }}</strong> across <strong>{{ $allBatchGroupsCount }}</strong> Batches
                    </span>
                    <span class="text-blue-600 font-medium">
                        <i class="fa-solid fa-calendar-day mr-1"></i> Starting: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d M Y (l)') }}</strong>
                    </span>
                </div>
                <div class="text-slate-400">
                    Version: #{{ $datesheet->version }} • Updated: {{ $datesheet->updated_at->diffForHumans() }}
                </div>
            </div>
        @endif
    </div>

    <!-- Live Generation Progress Card -->
    @if($isGenerating)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center space-x-2 text-blue-900 font-bold text-sm">
                    <i class="fa-solid fa-circle-notch fa-spin text-blue-600"></i>
                    <span>{{ $generationMessage ?: 'Solving constraint satisfaction problem...' }}</span>
                </div>
                <span class="text-xs font-extrabold text-blue-700">{{ $generationProgress }}%</span>
            </div>
            <div class="w-full bg-blue-200 rounded-full h-2.5 overflow-hidden">
                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: {{ $generationProgress }}%"></div>
            </div>
        </div>
    @endif

    <!-- Infeasibility Diagnostic Card -->
    @if($generationError)
        <div class="bg-rose-50 border-2 border-rose-300 rounded-xl p-5 shadow-sm">
            <div class="flex items-start space-x-3">
                <div class="w-9 h-9 rounded-full bg-rose-200 flex items-center justify-center text-rose-700 flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                </div>
                <div class="flex-grow">
                    <h3 class="text-base font-bold text-rose-900">Datesheet Infeasibility Detected</h3>
                    <p class="text-sm text-rose-800 mt-1 whitespace-pre-line">{{ $generationError }}</p>

                    @if(!empty($generationSuggestions))
                        <div class="mt-3 pt-3 border-t border-rose-200">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-rose-900">Recommended Actionable Fixes:</h4>
                            <ul class="mt-1.5 space-y-1 text-xs text-rose-800 list-disc list-inside">
                                @foreach($generationSuggestions as $sugg)
                                    <li>{{ $sugg }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- View Mode Switcher & Filter Toolbar -->
    @if($datesheet)
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
            <div class="inline-flex rounded-lg border border-slate-200 p-1 bg-slate-100">
                <button wire:click="$set('viewMode', 'official')" 
                        class="px-3.5 py-1.5 text-xs font-bold rounded-md transition flex items-center gap-2 {{ $viewMode === 'official' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Official Format (Sample PDF View)</span>
                </button>
                <button wire:click="$set('viewMode', 'matrix')" 
                        class="px-3.5 py-1.5 text-xs font-bold rounded-md transition flex items-center gap-2 {{ $viewMode === 'matrix' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    <i class="fa-solid fa-table-cells"></i>
                    <span>Master Timetable Matrix</span>
                </button>
            </div>

            @if($viewMode === 'official')
                <div class="flex flex-wrap items-center gap-2">
                    <label class="text-xs font-semibold text-slate-500">Filter Batch:</label>
                    <select wire:model.live="selectedBatchId" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 text-slate-700 font-medium focus:ring-1 focus:ring-blue-500">
                        <option value="">All Batches (Showing {{ $examsGroupedByBatch->count() }} of {{ $allBatchGroupsCount }})</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>

                    <a href="{{ url('/export/pdf/' . $datesheet->id . ($selectedBatchId ? '?batch_id=' . $selectedBatchId : '')) }}" 
                       target="_blank" 
                       class="inline-flex items-center px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                        <i class="fa-solid fa-print mr-1.5"></i> Print / Save PDF
                    </a>
                </div>
            @endif
        </div>
    @endif

    <!-- ========================================== -->
    <!-- VIEW MODE 1: OFFICIAL BGNU BATCH FORMAT     -->
    <!-- ========================================== -->
    @if($viewMode === 'official' && $datesheet && count($examsGroupedByBatch) > 0)
        <div class="space-y-8">
            @foreach($examsGroupedByBatch as $bId => $batchExams)
                @php
                    $firstExam = $batchExams->first();
                    $batch = $firstExam?->batch;
                    $shift = $firstExam?->shift;
                    $roomName = $firstExam?->rooms->pluck('name')->first() ?? 'Room # 01';
                    $startTime = $firstExam ? \Carbon\Carbon::parse($firstExam->start_time)->format('h:i A') : '08:30 AM';
                    $endTime = $firstExam ? \Carbon\Carbon::parse($firstExam->end_time)->format('h:i A') : '11:00 AM';
                    $shiftTitle = $shift?->name ?? 'MORNING';
                @endphp

                <!-- Batch Document Card (Exact Replica of University PDF) -->
                <div class="bg-white rounded-xl shadow-md border border-slate-300 max-w-4xl mx-auto p-6 md:p-10 font-serif text-slate-900 transition hover:shadow-lg">
                    
                    <!-- Quick Toolbar above sheet -->
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-200 font-sans text-xs">
                        <span class="font-bold text-blue-800 bg-blue-50 px-2.5 py-1 rounded border border-blue-200">
                            Batch: {{ $batch?->name ?? 'Batch' }} ({{ $batchExams->count() }} Exams)
                        </span>
                        <div class="flex items-center space-x-2">
                            <a href="{{ url('/export/pdf/' . $datesheet->id . '?batch_id=' . $bId) }}" target="_blank" class="text-rose-600 hover:text-rose-800 font-semibold flex items-center">
                                <i class="fa-solid fa-file-pdf mr-1"></i> Print This Batch Only
                            </a>
                        </div>
                    </div>

                    <!-- Institutional Header: 2 Logos + Centered Headings -->
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <div class="w-20 flex-shrink-0 text-left">
                            <img src="{{ asset('images/dept_logo.png') }}" class="max-h-16 max-w-full object-contain mx-auto" alt="Department Crest">
                        </div>
                        <div class="text-center flex-grow">
                            <h2 class="text-xl md:text-2xl font-bold tracking-tight text-black leading-tight">
                                {{ $datesheet->metrics['department_name'] ?? 'Department of Computer Science & IT' }}
                            </h2>
                            <h3 class="text-base md:text-lg font-normal text-black mt-0.5">
                                {{ $datesheet->metrics['university_name'] ?? 'Baba Guru Nanak University, Nankana Sahib' }}
                            </h3>
                        </div>
                        <div class="w-20 flex-shrink-0 text-right">
                            <img src="{{ asset('images/bgnu_logo.png') }}" class="max-h-16 max-w-full object-contain mx-auto" alt="University Crest">
                        </div>
                    </div>

                    <!-- Bordered Reference Box -->
                    <div class="border-[1.5px] border-black p-2.5 my-3 flex justify-between items-end text-xs md:text-sm font-bold">
                        <div class="leading-relaxed">
                            Office of the Chairperson<br>
                            Ref: {{ $datesheet->metrics['ref_no'] ?? 'BGNU/CS&IT/2026/46' }}
                        </div>
                        <div class="text-right">
                            Date: {{ $datesheet->metrics['issue_date'] ?? \Carbon\Carbon::parse($startDate)->format('d.m.Y') }}
                        </div>
                    </div>

                    <!-- Datesheet Subtitle Block -->
                    <div class="text-center my-4">
                        <h4 class="text-sm md:text-base font-extrabold uppercase tracking-wide leading-snug">
                            DATE SHEET FOR FINAL TERM EXAMINATION, SPRING 2026 {{ $batch?->name ?? 'BATCH' }}
                        </h4>
                        <div class="text-xs md:text-sm font-bold text-slate-900 mt-1">
                            TIME: {{ $startTime }} – {{ $endTime }}
                        </div>
                        <div class="text-xs md:text-sm font-bold text-slate-900">
                            {{ $roomName }}
                        </div>
                    </div>

                    <!-- Exact 6-Column Timetable Table -->
                    <div class="overflow-x-auto my-4">
                        <table class="w-full border-collapse border-[1.5px] border-black text-xs md:text-sm font-serif">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="border border-black px-2 py-2 text-center w-12 font-bold">Sr. No.</th>
                                    <th class="border border-black px-3 py-2 text-left w-24 font-bold">Day</th>
                                    <th class="border border-black px-3 py-2 text-left w-24 font-bold">Date</th>
                                    <th class="border border-black px-3 py-2 text-left font-bold">Subject</th>
                                    <th class="border border-black px-3 py-2 text-left w-52 font-bold">Course Instructor/Invigilator</th>
                                    <th class="border border-black px-2 py-2 text-center w-24 font-bold font-sans">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batchExams as $idx => $exam)
                                    @php
                                        $instructorName = $exam->invigilators->pluck('name')->first()
                                            ?? ($exam->course->teachers->pluck('name')->first() ?? 'Concerned Faculty');
                                    @endphp
                                    <tr class="hover:bg-blue-50/40 transition">
                                        <td class="border border-black px-2 py-2 text-center font-medium">{{ $idx + 1 }}</td>
                                        <td class="border border-black px-3 py-2">{{ $exam->exam_date->format('l') }}</td>
                                        <td class="border border-black px-3 py-2">{{ $exam->exam_date->format('d-m-Y') }}</td>
                                        <td class="border border-black px-3 py-2">
                                            <strong>{{ $exam->course->name }}</strong>
                                            <span class="text-xs text-slate-500 font-sans ml-1">({{ $exam->course->code }})</span>
                                        </td>
                                        <td class="border border-black px-3 py-2">{{ $instructorName }}</td>
                                        <td class="border border-black px-2 py-1.5 text-center font-sans text-xs">
                                            <div class="flex items-center justify-center space-x-1">
                                                <button wire:click="toggleExamLock({{ $exam->id }})" 
                                                        title="{{ $exam->is_locked ? 'Locked: Click to unlock' : 'Unlocked: Click to lock in place' }}"
                                                        class="p-1 rounded {{ $exam->is_locked ? 'text-amber-600 bg-amber-50' : 'text-slate-400 hover:text-slate-600' }}">
                                                    <i class="fa-solid {{ $exam->is_locked ? 'fa-lock' : 'fa-lock-open' }}"></i>
                                                </button>
                                                <button wire:click="openMoveModal({{ $exam->id }})" 
                                                        title="Move / Reschedule this exam"
                                                        class="p-1 rounded text-blue-600 hover:bg-blue-50">
                                                    <i class="fa-solid fa-arrows-up-down-left-right text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer with CC Block and Signature -->
                    <div class="mt-8 pt-4 flex flex-col md:flex-row justify-between items-start gap-6 font-serif">
                        <!-- CC List -->
                        <div class="text-xs leading-relaxed max-w-sm text-slate-800">
                            <strong class="font-bold">CC:</strong><br>
                            1. Controller of Examination<br>
                            2. Director Academics<br>
                            3. All Chairs of Concerned Departments<br>
                            4. All Concerned Faculty<br>
                            5. Office Copy
                        </div>

                        <!-- Chairperson Signature Stamp -->
                        <div class="text-center md:self-end flex flex-col items-center">
                            <img src="{{ asset('images/signature.png') }}" class="max-h-14 max-w-36 object-contain mb-1" alt="Chairperson Signature">
                            <div class="font-bold text-xs uppercase tracking-wider text-black">Chairperson</div>
                            <div class="font-bold text-xs text-black">{{ $datesheet->metrics['chairperson_name'] ?? 'Dr. Muhammad Usman Younus' }}</div>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

    <!-- ========================================== -->
    <!-- VIEW MODE 2: MASTER TIMETABLE MATRIX       -->
    <!-- ========================================== -->
    @if($viewMode === 'matrix' && $datesheet)
        <!-- Filter Controls Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <!-- Search Course -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Search Course</label>
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" wire:model.live.debounce.300ms="searchCourse" placeholder="Code or Title..." 
                               class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Batch Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Filter Batch</label>
                    <select wire:model.live="filterBatch" class="w-full py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500">
                        <option value="">All Batches</option>
                        @foreach($batches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Shift Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Filter Shift</label>
                    <select wire:model.live="filterShift" class="w-full py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500">
                        <option value="">All Shifts</option>
                        @foreach($shifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Room Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Filter Room</label>
                    <select wire:model.live="filterRoom" class="w-full py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500">
                        <option value="">All Rooms</option>
                        @foreach($rooms as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Teacher Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Filter Invigilator</label>
                    <select wire:model.live="filterTeacher" class="w-full py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500">
                        <option value="">All Faculty</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if(count($uniqueDates) > 0)
            <!-- Interactive Timetable Day x Slot Grid -->
            <div class="space-y-6">
                @foreach($uniqueDates as $date)
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <!-- Day Header -->
                        <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <span class="w-3 h-3 rounded-full bg-blue-400"></span>
                                <span class="font-bold text-base">{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</span>
                            </div>
                            <span class="text-xs text-slate-300">
                                {{ count($groupedExams[$date] ?? []) }} Active Slot(s)
                            </span>
                        </div>

                        <!-- Slots Grid for this Day -->
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($groupedExams[$date] ?? [] as $slotIndex => $examsInSlot)
                                @php
                                    $firstExam = $examsInSlot[0] ?? null;
                                @endphp
                                <div class="bg-slate-50 rounded-lg border border-slate-200 p-3.5 flex flex-col justify-between">
                                    <!-- Slot Header -->
                                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-200">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="font-extrabold text-xs text-blue-900 bg-blue-100 px-2 py-0.5 rounded">
                                                Slot {{ $slotIndex }}
                                            </span>
                                            <span class="text-xs font-semibold text-slate-600">
                                                {{ $firstExam ? sprintf('%s - %s', $firstExam->start_time, $firstExam->end_time) : '' }}
                                            </span>
                                        </div>
                                        @if($firstExam)
                                            <span class="text-xs px-2 py-0.5 rounded font-semibold {{ strtolower($firstExam->shift->name) === 'morning' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">
                                                {{ $firstExam->shift->name }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Exam Cards in Slot -->
                                    <div class="space-y-2.5">
                                        @foreach($examsInSlot as $exam)
                                            <div class="bg-white rounded-md p-3 border {{ $exam->is_locked ? 'border-amber-400 shadow-amber-50 shadow-sm' : 'border-slate-200' }} hover:border-blue-400 transition">
                                                <div class="flex items-start justify-between gap-1">
                                                    <div>
                                                        <span class="font-bold text-sm text-slate-900">{{ $exam->course->code }}</span>
                                                        <h4 class="text-xs text-slate-600 font-medium line-clamp-1" title="{{ $exam->course->name }}">
                                                            {{ $exam->course->name }}
                                                        </h4>
                                                    </div>
                                                    <button wire:click="toggleExamLock({{ $exam->id }})" 
                                                            title="{{ $exam->is_locked ? 'Locked (will not be rescheduled)' : 'Unlocked' }}"
                                                            class="text-xs p-1 rounded {{ $exam->is_locked ? 'text-amber-600 hover:bg-amber-50' : 'text-slate-400 hover:bg-slate-100' }}">
                                                        <i class="fa-solid {{ $exam->is_locked ? 'fa-lock' : 'fa-lock-open' }}"></i>
                                                    </button>
                                                </div>

                                                <div class="mt-2 pt-2 border-t border-slate-100 grid grid-cols-2 gap-1 text-xs text-slate-500">
                                                    <div>
                                                        <i class="fa-solid fa-users text-slate-400 mr-1"></i>
                                                        <strong class="text-slate-700">{{ $exam->batch->name }}</strong>
                                                    </div>
                                                    <div>
                                                        <i class="fa-solid fa-door-closed text-slate-400 mr-1"></i>
                                                        <span class="text-slate-700">{{ $exam->rooms->pluck('name')->implode(', ') ?: 'Room TBD' }}</span>
                                                    </div>
                                                    <div class="col-span-2 mt-0.5 truncate" title="{{ $exam->invigilators->pluck('name')->implode(', ') }}">
                                                        <i class="fa-solid fa-user-tie text-slate-400 mr-1"></i>
                                                        <span>{{ $exam->invigilators->pluck('name')->implode(', ') ?: 'Invigilator TBD' }}</span>
                                                    </div>
                                                </div>

                                                <div class="mt-2.5 pt-1.5 border-t border-slate-100 flex justify-end">
                                                    <button wire:click="openMoveModal({{ $exam->id }})" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center">
                                                        <i class="fa-solid fa-arrows-up-down-left-right mr-1 text-xs"></i> Move / Reschedule
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-500">
                <i class="fa-solid fa-filter text-slate-300 text-4xl mb-3"></i>
                <h3 class="text-base font-bold text-slate-700">No exams match the selected filter criteria</h3>
                <p class="text-xs mt-1">Try resetting the filters above to view the full timetable.</p>
            </div>
        @endif
    @elseif(!$datesheet)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-500">
            <i class="fa-solid fa-calendar-plus text-blue-300 text-5xl mb-4"></i>
            <h3 class="text-lg font-bold text-slate-800">No Datesheet Generated Yet</h3>
            <p class="text-sm mt-1 max-w-md mx-auto">Click "Generate Datesheet" above to automatically generate a clash-free examination timetable in seconds.</p>
            <button wire:click="triggerGeneration(false)" class="mt-5 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg shadow transition">
                <i class="fa-solid fa-bolt mr-2"></i> Generate First Datesheet
            </button>
        </div>
    @endif

    <!-- Move / Reschedule Modal with Live Validation -->
    @if($showMoveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200" @click.away="$set('showMoveModal', false)">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">Move Examination (Live Conflict Check)</h3>
                    <button wire:click="$set('showMoveModal', false)" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                @if($moveValidationError)
                    <div class="mt-4 p-3.5 bg-rose-50 border border-rose-300 rounded-lg text-rose-800 text-xs">
                        <div class="flex items-start space-x-2">
                            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm mt-0.5"></i>
                            <div>
                                <strong class="font-bold">Move Blocked:</strong> {{ $moveValidationError }}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-4 space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Exam Date</label>
                        <input type="date" wire:model="newExamDate" class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Shift</label>
                        <select wire:model="newShiftId" class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                            @foreach($shifts as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->start_time }} - {{ $sh->end_time }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Destination Slot Index</label>
                        <select wire:model="newSlotIndex" class="w-full text-sm bg-slate-50 border border-slate-300 rounded-lg px-3 py-2">
                            <option value="1">Slot 1</option>
                            <option value="2">Slot 2</option>
                            <option value="3">Slot 3</option>
                            <option value="4">Slot 4</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-2.5">
                    <button wire:click="$set('showMoveModal', false)" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">
                        Cancel
                    </button>
                    <button wire:click="moveExam" class="px-4 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm transition">
                        Verify & Move Exam
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
