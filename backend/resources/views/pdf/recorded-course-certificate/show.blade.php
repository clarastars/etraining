<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="stylesheet" href="{{ public_path('pdf.css') }}">
    <title>شهادة حضور دورة تطويرية</title>
    <style>
        * { direction: rtl; }
        #page-cover {
            position: absolute;
            top: 0;
            width: 100% !important;
            background-repeat: no-repeat;
            left: 0;
            height: 1017px;
        }
        .content { text-align: center; padding: 2rem; }
        h1 { font-size: 72px; color: rgb(192, 0, 0); }
        p { font-size: 30px; direction: rtl; font-weight: bold; }
        #page-stamp { max-width: 270px; margin-top: -35px; }
        .small-text { font-size: 20px; }
    </style>
</head>
<body>
<div class="content">
    <img id="page-cover" src="{{ public_path('/img/Certificate-new-bg.jpg') }}"/>
    <div class="text">
        <div class="row" style="margin-top: 2rem;">
            <h1 style="margin-top:8rem;">سجل حضور دورة تطويرية</h1>
            <p style="margin-top:2.5rem;">بهذا نفيد أن المتدرب/ة "{{ $traineeName }}"</p>
            <p style="margin-top:2.5rem;">بموجب السجل المدني رقم "{{ $identityNumber }}"</p>
            <p style="margin-top:2.5rem;">قـــد حضرت دورة ({{ $courseName }}) </p>
            <p style="margin-top:5rem;">والله الموفق،،،</p>
            <div class="col-7">
                <p style="margin-bottom:0;padding-bottom: 0"><b>ختم المنشأة التدريبية</b></p>
                <img style="padding-top: 20px" id="page-stamp" src="{{ public_path('/img/certificate-stamp_without_number.png') }}">
            </div>
            <div class="col-4" style="text-align: right;">
                <p>توقيع المديرة: <img id="page-stamp" src="{{ public_path('/img/signature.png') }}"></p>
                <p class="small-text">ملاحظة: أي كشط او تعديل على الشهادة تعتبر لاغية</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
