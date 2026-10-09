// Interaksi kecil di sisi browser (tanpa library eksternal).
document.addEventListener('DOMContentLoaded', function () {
    // Menu mobile
    var toggle = document.querySelector('[data-toggle="nav"]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.getElementById('nav').classList.toggle('open');
        });
    }

    // Konfirmasi sebelum aksi berbahaya (hapus, dll.)
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        });
    });

    // Konfirmasi sebelum mengirim jawaban kuis secara manual
    var quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function (ev) {
            if (!window.confirm('Kirim jawaban sekarang? Jawaban tidak bisa diubah lagi.')) {
                ev.preventDefault();
            }
        });
    }

    // Tombol cetak
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Salin teks (kode kelas)
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(btn.getAttribute('data-copy'));
                btn.textContent = 'Tersalin!';
            }
        });
    });

    // Timer kuis: kirim otomatis saat waktu habis
    var timer = document.getElementById('quiz-timer');
    if (timer) {
        var remaining = parseInt(timer.getAttribute('data-remaining'), 10);
        var form = document.getElementById('quiz-form');
        var label = timer.querySelector('span');
        var submitted = false;
        form.addEventListener('submit', function () { submitted = true; });
        var tick = function () {
            if (remaining <= 0) {
                label.textContent = 'Waktu habis';
                if (!submitted) { submitted = true; form.submit(); }
                return;
            }
            var m = Math.floor(remaining / 60), s = remaining % 60;
            label.textContent = '⏱ ' + m + ':' + (s < 10 ? '0' : '') + s;
            remaining--;
            setTimeout(tick, 1000);
        };
        tick();
    }
});
