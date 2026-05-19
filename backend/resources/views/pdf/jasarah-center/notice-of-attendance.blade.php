<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 23px;
            color: #1a1a1a;
        }

        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            height: 297mm;
            margin: 0;
            padding: 0;
        }

        .content {
            width: 100%;
            padding: 18mm 20mm 20mm;
            text-align: center;
        }

        .page-footer {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 210mm;
            height: 12px;
            background-color: #2b65ea;
        }

        .logo img {
            width: 220px;
            height: auto;
            display: block;
            margin: 0 auto 56px;
        }

        h1 {
            font-size: 39px;
            font-weight: bold;
            margin: 0 0 40px;
            letter-spacing: 0.3px;
        }

        .line {
            font-size: 23px;
            line-height: 1.9;
            margin: 0 0 20px;
        }

        .trainee-name {
            font-size: 29px;
            font-weight: bold;
            margin: 0 0 20px;
            text-decoration: underline;
        }

        .course-name {
            font-size: 27px;
            font-weight: bold;
            margin: 0 0 20px;
        }

        .disclaimer {
            font-size: 22px;
            line-height: 1.8;
            margin: 0 0 32px;
        }

        .wish {
            font-size: 23px;
            line-height: 1.8;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="content">
            <div class="logo">
                <img src="{{ public_path('img/jasarah-logo.png') }}" alt="Jasarah">
            </div>

            <h1>Notice of Attendance</h1>

            <p class="line">The Jasarah Center reports</p>
            <p class="line">That the trainee:</p>
            <p class="trainee-name">{{ $trainee_name }}</p>
            <p class="line">Had attended the program titled:</p>
            <p class="course-name">{{ $course_name }}</p>
            <p class="line">And issued on {{ $date }} based on the trainee&rsquo;s request.</p>

            <p class="disclaimer">
                This notice was granted based on attendance, and does not constitute<br>
                professional certification.
            </p>

            <p class="wish">Wishing you continued success in all your future endeavors</p>
        </div>

        <div class="page-footer"></div>
    </div>
</body>
</html>
