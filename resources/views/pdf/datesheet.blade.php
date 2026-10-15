<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $datesheet->name }} - Official Datesheet (BGNU)</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 15mm 12mm 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif, 'Segoe UI', Tahoma, sans-serif;
            color: #000000;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            font-size: 10pt;
            line-height: 1.25;
        }

        /* Screen-only action toolbar */
        .print-btn-bar {
            background-color: #1e3a8a;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-radius: 6px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .btn {
            background: white;
            color: #1e3a8a;
            border: none;
            padding: 7px 14px;
            font-weight: bold;
            font-size: 13px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
            margin-left: 6px;
        }
        .batch-select {
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 13px;
            margin-right: 8px;
            border: 1px solid #cbd5e1;
        }

        /* Batch Page Layout (Exact Reproduction of BGNU PDF) */
        .batch-page {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 35px auto;
            padding: 15px 20px;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .header-grid {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .header-cell {
            display: table-cell;
            vertical-align: middle;
        }
        .logo-left {
            width: 80px;
            text-align: left;
        }
        .logo-right {
            width: 80px;
            text-align: right;
        }
        .header-center {
            text-align: center;
        }
        .logo-img {
            max-height: 65px;
            max-width: 75px;
            object-fit: contain;
        }
        .dept-title {
            font-size: 15.5pt;
            font-weight: bold;
            margin: 0;
            color: #000000;
            letter-spacing: 0.2px;
        }
        .uni-title {
            font-size: 12.5pt;
            font-weight: normal;
            margin: 2px 0 0 0;
            color: #000000;
        }

        /* Bordered Reference Box */
        .ref-box {
            border: 1.5px solid #000000;
            padding: 4px 10px;
            margin-top: 6px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            font-size: 9.5pt;
            font-weight: bold;
        }
        .ref-left {
            text-align: left;
            line-height: 1.35;
        }
        .ref-right {
            text-align: right;
            align-self: flex-end;
        }

        /* Datesheet Subtitle */
        .datesheet-title {
            text-align: center;
            font-size: 10pt;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.4;
            margin-bottom: 12px;
        }
        .datesheet-time {
            font-size: 9.5pt;
            font-weight: 700;
        }
        .datesheet-room {
            font-size: 9.5pt;
            font-weight: 700;
        }

        /* Timetable Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 15px;
            border: 1.5px solid #000000;
        }
        th, td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        th {
            font-weight: bold;
            text-align: left;
            background-color: #ffffff;
            font-size: 9pt;
        }
        th.center, td.center {
            text-align: center;
        }

        /* Footer & Signatures */
        .footer-container {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            page-break-inside: avoid;
        }
        .cc-block {
            font-size: 8.5pt;
            line-height: 1.45;
            width: 50%;
        }
        .cc-title {
            font-weight: bold;
            margin-bottom: 2px;
        }
        .sig-block {
            text-align: center;
            width: 40%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: -10px;
        }
        .sig-img {
            max-height: 55px;
            max-width: 140px;
            margin-bottom: 2px;
        }
        .sig-role {
            font-size: 9.5pt;
            font-weight: bold;
            margin-top: 2px;
        }
        .sig-name {
            font-size: 9.5pt;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .batch-page {
                page-break-after: always;
                page-break-inside: avoid;
                margin: 0;
                padding: 0;
                max-width: 100%;
                height: 98vh;
            }
            .batch-page:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (Hidden when printing) -->
    <div class="print-btn-bar no-print">
        <div>
            <strong>{{ $datesheet->metrics['university_name'] ?? 'Baba Guru Nanak University' }} — Official Examination Datesheet</strong> (v{{ $datesheet->version }})
        </div>
        <div style="display: flex; align-items: center;">
            <select class="batch-select" onchange="window.location.href='{{ url('/export/pdf/' . $datesheet->id) }}' + (this.value ? '?batch_id=' + this.value : '')">
                <option value="">All Batches (Full Multi-Page Document: {{ count($allBatches) }} Pages)</option>
                @foreach($allBatches as $b)
                    <option value="{{ $b->id }}" {{ $batchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
            <button onclick="window.print()" class="btn">🖨️ Print / Save as PDF</button>
            <a href="{{ url('/') }}" class="btn btn-secondary">Back to Timetable</a>
        </div>
    </div>

    @foreach($examsGroupedByBatch as $bId => $batchExams)
        @php
            $firstExam = $batchExams->first();
            $batch = $firstExam?->batch;
            $shift = $firstExam?->shift;
            $roomName = $firstExam?->rooms->pluck('name')->first() ?? 'Room # 01';
            $startTime = $firstExam ? \Carbon\Carbon::parse($firstExam->start_time)->format('h:i A') : '09:00 AM';
            $endTime = $firstExam ? \Carbon\Carbon::parse($firstExam->end_time)->format('h:i A') : '11:00 AM';
            $shiftTitle = $shift?->name ?? 'MORNING';

            $leftLogo = $datesheet->metrics['left_logo'] ?? 'images/dept_logo.png';
            $rightLogo = $datesheet->metrics['right_logo'] ?? 'images/bgnu_logo.png';
            $sigImg = $datesheet->metrics['signature_img'] ?? 'images/signature.png';

            $ccRaw = $datesheet->metrics['cc_list'] ?? "1. Controller of Examination\n2. Director Academics\n3. All Chairs of Concerned Departments\n4. All Concerned Faculty\n5. Office Copy";
            $ccLines = is_array($ccRaw) ? $ccRaw : explode("\n", str_replace("\r", "", (string)$ccRaw));
        @endphp

        <div class="batch-page">
            <!-- Header section with 2 crests -->
            <div class="header-grid">
                <div class="header-cell logo-left">
                    @if($leftLogo && file_exists(public_path($leftLogo)))
                        <img src="{{ asset($leftLogo) }}" class="logo-img" alt="Dept Logo">
                    @endif
                </div>
                <div class="header-cell header-center">
                    <h1 class="dept-title">{{ $datesheet->metrics['department_name'] ?? 'Department of Computer Science & IT' }}</h1>
                    <h2 class="uni-title">{{ $datesheet->metrics['university_name'] ?? 'Baba Guru Nanak University, Nankana Sahib' }}</h2>
                </div>
                <div class="header-cell logo-right">
                    @if($rightLogo && file_exists(public_path($rightLogo)))
                        <img src="{{ asset($rightLogo) }}" class="logo-img" alt="Uni Logo">
                    @endif
                </div>
            </div>

            <!-- Bordered Ref & Date Box -->
            <div class="ref-box">
                <div class="ref-left">
                    Office of the {{ $datesheet->metrics['chairperson_title'] ?? 'Chairperson' }}<br>
                    Ref: {{ $datesheet->metrics['ref_no'] ?? 'BGNU/CS&IT/2026/46' }}
                </div>
                <div class="ref-right">
                    Date: {{ $datesheet->metrics['issue_date'] ?? '03.06.2026' }}
                </div>
            </div>

            <!-- Datesheet Subtitle -->
            <div class="datesheet-title">
                {{ $datesheet->metrics['exam_title'] ?? 'DATE SHEET FOR FINAL TERM EXAMINATION' }}, {{ $datesheet->metrics['session_term'] ?? 'SPRING 2026' }} {{ $batch?->name ?? 'BATCH' }}<br>
                <span class="datesheet-time">TIME: {{ $startTime }} – {{ $endTime }}</span><br>
                <span class="datesheet-room">{{ $roomName }}</span>
            </div>

            <!-- Examination Timetable Table -->
            <table>
                <thead>
                    <tr>
                        <th style="width: 7%;" class="center">Sr. No.</th>
                        <th style="width: 14%;">Day</th>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 32%;">Subject</th>
                        <th style="width: 25%;">Course Instructor/Invigilator</th>
                        <th style="width: 8%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batchExams as $idx => $exam)
                        @php
                            $instructorName = $exam->invigilators->pluck('name')->first()
                                ?? ($exam->course->teachers->pluck('name')->first() ?? 'Concerned Faculty');
                        @endphp
                        <tr>
                            <td class="center">{{ $idx + 1 }}</td>
                            <td>{{ $exam->exam_date->format('l') }}</td>
                            <td>{{ $exam->exam_date->format('d-m-Y') }}</td>
                            <td><strong>{{ $exam->course->name }}</strong></td>
                            <td>{{ $instructorName }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Signatures and CC Block -->
            <div class="footer-container">
                <div class="cc-block">
                    <div class="cc-title">CC:</div>
                    @foreach($ccLines as $ccItem)
                        @if(trim($ccItem))
                            {{ trim($ccItem) }}<br>
                        @endif
                    @endforeach
                </div>

                <div class="sig-block">
                    @if($sigImg && file_exists(public_path($sigImg)))
                        <img src="{{ asset($sigImg) }}" class="sig-img" alt="Chairperson Signature">
                    @else
                        <div style="height: 35px; border-bottom: 1.5px dotted #000; width: 140px; margin-bottom: 5px;"></div>
                    @endif
                    <div class="sig-role">{{ $datesheet->metrics['chairperson_title'] ?? 'Chairperson' }}</div>
                    <div class="sig-name">{{ $datesheet->metrics['chairperson_name'] ?? 'Dr. Muhammad Usman Younus' }}</div>
                </div>
            </div>
        </div>
    @endforeach

</body>
</html>
