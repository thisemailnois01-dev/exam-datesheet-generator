<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center">
                <i class="fa-solid fa-file-excel text-emerald-600 mr-2.5"></i> Data Management & Excel Import
            </h1>
            <p class="text-sm text-slate-500 mt-1">Download official pre-formatted Excel templates and bulk import courses, rooms, teachers, and student enrollments with instant row-level error validation.</p>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if($importSuccessMessage)
        <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-800 text-sm flex items-center">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg mr-2.5"></i>
            <span>{{ $importSuccessMessage }}</span>
        </div>
    @endif

    @if(!empty($importErrors))
        <div class="p-4 rounded-lg bg-rose-50 border border-rose-300 text-rose-900 text-xs">
            <div class="flex items-center space-x-2 font-bold mb-2 text-sm text-rose-800">
                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                <span>Import Validation Errors Detected (Row-Level Diagnostics):</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-rose-800">
                @foreach($importErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Downloadable Templates Section -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h3 class="text-base font-bold text-slate-900 mb-2 flex items-center">
            <i class="fa-solid fa-download text-blue-600 mr-2"></i> Downloadable Official Excel Templates
        </h3>
        <p class="text-xs text-slate-500 mb-4">Click any template below to download the pre-formatted Excel spreadsheet with headers and sample rows.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ url('/export/template/courses') }}" class="p-3.5 rounded-lg border border-slate-200 hover:border-blue-500 hover:shadow-sm bg-slate-50 flex items-center space-x-3 transition">
                <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div>
                    <strong class="text-xs text-slate-800 block">Courses Template</strong>
                    <span class="text-[11px] text-blue-600 font-semibold">Download .xlsx</span>
                </div>
            </a>

            <a href="{{ url('/export/template/rooms') }}" class="p-3.5 rounded-lg border border-slate-200 hover:border-amber-500 hover:shadow-sm bg-slate-50 flex items-center space-x-3 transition">
                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-door-open"></i>
                </div>
                <div>
                    <strong class="text-xs text-slate-800 block">Rooms Template</strong>
                    <span class="text-[11px] text-amber-600 font-semibold">Download .xlsx</span>
                </div>
            </a>

            <a href="{{ url('/export/template/teachers') }}" class="p-3.5 rounded-lg border border-slate-200 hover:border-emerald-500 hover:shadow-sm bg-slate-50 flex items-center space-x-3 transition">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <strong class="text-xs text-slate-800 block">Teachers Template</strong>
                    <span class="text-[11px] text-emerald-600 font-semibold">Download .xlsx</span>
                </div>
            </a>

            <a href="{{ url('/export/template/students') }}" class="p-3.5 rounded-lg border border-slate-200 hover:border-indigo-500 hover:shadow-sm bg-slate-50 flex items-center space-x-3 transition">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <strong class="text-xs text-slate-800 block">Students Template</strong>
                    <span class="text-[11px] text-indigo-600 font-semibold">Download .xlsx</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Bulk Upload Panels -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Import Courses -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h4 class="text-sm font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-file-arrow-up text-blue-600 mr-2"></i> Import Course Offerings
            </h4>
            <p class="text-xs text-slate-500">Imports course code, title, batch assignment, credits, type, and core/hard tags.</p>
            <input type="file" wire:model="coursesFile" accept=".xlsx,.xls,.csv" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <button wire:click="importCourses" wire:loading.attr="disabled" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="importCourses">Upload & Validate Courses</span>
                <span wire:loading wire:target="importCourses">Importing...</span>
            </button>
        </div>

        <!-- Import Rooms -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h4 class="text-sm font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-file-arrow-up text-amber-600 mr-2"></i> Import Examination Rooms
            </h4>
            <p class="text-xs text-slate-500">Imports room name, seating capacity, room type, and active status.</p>
            <input type="file" wire:model="roomsFile" accept=".xlsx,.xls,.csv" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
            <button wire:click="importRooms" wire:loading.attr="disabled" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="importRooms">Upload & Validate Rooms</span>
                <span wire:loading wire:target="importRooms">Importing...</span>
            </button>
        </div>

        <!-- Import Teachers -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h4 class="text-sm font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-file-arrow-up text-emerald-600 mr-2"></i> Import Teachers / Invigilators
            </h4>
            <p class="text-xs text-slate-500">Imports teacher names, university emails, max duties per day and per session.</p>
            <input type="file" wire:model="teachersFile" accept=".xlsx,.xls,.csv" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <button wire:click="importTeachers" wire:loading.attr="disabled" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="importTeachers">Upload & Validate Teachers</span>
                <span wire:loading wire:target="importTeachers">Importing...</span>
            </button>
        </div>

        <!-- Import Students -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h4 class="text-sm font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-file-arrow-up text-indigo-600 mr-2"></i> Import Students & Enrollments
            </h4>
            <p class="text-xs text-slate-500">Imports student roll numbers, batch assignments, and course enrollments.</p>
            <input type="file" wire:model="studentsFile" accept=".xlsx,.xls,.csv" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <button wire:click="importStudents" wire:loading.attr="disabled" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="importStudents">Upload & Validate Enrollments</span>
                <span wire:loading wire:target="importStudents">Importing...</span>
            </button>
        </div>

    </div>

</div>
