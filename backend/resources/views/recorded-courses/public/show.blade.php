@extends('recorded-courses.public.layout')

@section('title', ($course->name_ar ?: $course->name_en) . ' | جسارة')

@section('header_actions')
    @if($checkedIn)
        <form method="POST" action="{{ route('recorded-courses.public.check-out', ['token' => $token]) }}">
            @csrf
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-full border border-jasarah/15 bg-white px-4 py-2 text-sm font-bold text-jasarah shadow-sm transition hover:border-jasarah/40"
            >
                تسجيل الخروج (Check-out)
            </button>
        </form>
    @endif
@endsection

@section('content')
    <section class="bg-jasarah">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <p class="text-sm font-bold text-white/80">محتوى الدورة المسجلة</p>
            <h1 class="mt-2 max-w-4xl text-3xl font-extrabold leading-tight text-white sm:text-4xl">
                {{ $course->name_ar ?: $course->name_en }}
            </h1>
            <p class="mt-3 text-sm font-medium text-white/85">
                {{ $trainee->name ?? '' }} · {{ $lessons->count() }} دروس
            </p>
            @if($enrollment->completed_at)
                <p class="mt-2 inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold text-white">
                    اكتملت الدورة — بانتظار اعتماد الشهادة
                </p>
            @endif
        </div>
    </section>

    @if(session('errors'))
        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @unless($checkedIn)
        <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="rounded-[2rem] border border-jasarah/10 bg-white p-8 text-center shadow-xl shadow-jasarah/10">
                <h2 class="text-2xl font-extrabold text-jasarah">تسجيل الحضور</h2>
                <p class="mt-3 text-sm leading-7 text-jasarah-muted">
                    اضغط على «تسجيل الدخول» لبدء متابعة الدورة. يتم تسجيل وقت الحضور لأغراض الإفصاح والتوثيق.
                </p>
                <form method="POST" action="{{ route('recorded-courses.public.check-in', ['token' => $token]) }}" class="mt-8">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-jasarah px-8 py-3 text-sm font-extrabold text-white shadow-lg shadow-jasarah/30 transition hover:bg-jasarah-dark"
                    >
                        تسجيل الدخول (Check-in)
                    </button>
                </form>
            </div>
        </section>
    @else
        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                @if($canUnlock)
                    <form method="POST" action="{{ route('recorded-courses.public.unlock', ['token' => $token]) }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-jasarah px-5 py-2.5 text-sm font-extrabold text-white shadow-md shadow-jasarah/25 transition hover:bg-jasarah-dark"
                        >
                            فتح فيديو اليوم
                        </button>
                    </form>
                @endif
                <p class="text-sm text-jasarah-muted">
                    وقت الحضور: {{ $enrollment->checked_in_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    @if($enrollment->checked_out_at)
                        · الخروج: {{ $enrollment->checked_out_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    @endif
                </p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
                <div class="overflow-hidden rounded-[2rem] border border-jasarah/10 bg-white shadow-xl shadow-jasarah/10">
                    <div class="bg-black">
                        <video
                            id="course-player"
                            class="aspect-video w-full bg-black"
                            controls
                            playsinline
                            preload="metadata"
                            @if($firstStreamable)
                                src="{{ $firstStreamable['stream_url'] }}"
                            @endif
                        >
                            متصفحك لا يدعم تشغيل الفيديو.
                        </video>
                    </div>
                    <div class="space-y-2 p-5 sm:p-6">
                        <h2 id="now-playing-title" class="text-xl font-extrabold text-jasarah-ink sm:text-2xl">
                            {{ $firstStreamable['title_ar'] ?? ($firstStreamable['title_en'] ?? 'اختر درساً من القائمة') }}
                        </h2>
                        <p class="text-sm text-jasarah-muted">اضغط على درس مفتوح للتشغيل، ثم علّمه مكتملاً بعد المشاهدة.</p>
                    </div>
                </div>

                <aside class="overflow-hidden rounded-[2rem] border border-jasarah/10 bg-white shadow-xl shadow-jasarah/10">
                    <div class="border-b border-jasarah/10 bg-jasarah-tint px-5 py-4">
                        <h2 class="text-lg font-extrabold text-jasarah">دروس الدورة</h2>
                        <p class="mt-1 text-sm text-jasarah-muted">افتح الدرس ثم أكمله بالترتيب</p>
                    </div>

                    <div class="max-h-[70vh] space-y-2 overflow-y-auto p-3 sm:p-4" id="lesson-list">
                        @foreach($lessons as $index => $lesson)
                            @php
                                $isActive = $firstStreamable && $firstStreamable['id'] === $lesson['id'];
                            @endphp
                            <div class="rounded-2xl border border-jasarah/10 bg-jasarah-page p-3">
                                <button
                                    type="button"
                                    class="lesson-btn w-full rounded-2xl px-3 py-3 text-right transition {{ $lesson['can_stream'] ? ($isActive ? 'lesson-active' : 'bg-white hover:bg-jasarah-tint') : 'cursor-not-allowed bg-gray-100 text-gray-400' }}"
                                    @if($lesson['can_stream'])
                                        data-stream-url="{{ $lesson['stream_url'] }}"
                                        data-title="{{ $lesson['title_ar'] ?: $lesson['title_en'] }}"
                                    @endif
                                    @unless($lesson['can_stream']) disabled @endunless
                                >
                                    <span class="block text-sm font-extrabold leading-6">
                                        {{ $index + 1 }}. {{ $lesson['title_ar'] ?: $lesson['title_en'] }}
                                    </span>
                                    <span class="mt-1 block text-xs font-bold opacity-80">
                                        @if($lesson['completed_at'])
                                            مكتمل
                                        @elseif($lesson['unlocked_at'])
                                            مفتوح
                                        @else
                                            مغلق
                                        @endif
                                    </span>
                                </button>

                                @if($lesson['unlocked_at'] && ! $lesson['completed_at'])
                                    <form
                                        method="POST"
                                        action="{{ route('recorded-courses.public.complete', ['token' => $token, 'lesson' => $lesson['id']]) }}"
                                        class="mt-2"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full rounded-xl border border-jasarah/20 bg-white px-3 py-2 text-xs font-extrabold text-jasarah transition hover:border-jasarah/40"
                                        >
                                            تعليم الدرس كمكتمل
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </aside>
            </div>
        </section>
    @endunless
@endsection

@push('scripts')
<script>
    (function () {
        var player = document.getElementById('course-player');
        if (!player) return;
        var titleEl = document.getElementById('now-playing-title');
        var buttons = document.querySelectorAll('.lesson-btn[data-stream-url]');

        function activate(button) {
            buttons.forEach(function (btn) {
                btn.classList.remove('lesson-active');
                btn.classList.add('bg-white');
            });
            button.classList.add('lesson-active');
            button.classList.remove('bg-white');

            var url = button.getAttribute('data-stream-url');
            var title = button.getAttribute('data-title') || '';
            if (titleEl) titleEl.textContent = title;

            if (player.getAttribute('src') !== url) {
                player.setAttribute('src', url);
                player.load();
            }
            player.play().catch(function () {});
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                activate(button);
            });
        });
    })();
</script>
@endpush
