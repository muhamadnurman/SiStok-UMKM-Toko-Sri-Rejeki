/* Bantuan isian form:
   .input-rupiah -> harga bertitik ribuan (72.000)
   .input-satuan -> huruf awal besar (Pcs, Kg, Pack)
   .input-angka  -> angka saja, tanpa nol di depan (stok) */
(function () {
    function grup(digits) {
        digits = String(digits).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function hurufAwalBesar(s) {
        return s.toLowerCase().replace(/(^|\s)(\S)/g, function (m, spasi, huruf) {
            return spasi + huruf.toUpperCase();
        });
    }

    window.setRupiah = function (el, nilai) {
        var n = Math.round(parseFloat(nilai));
        el.value = (isFinite(n) && n > 0) ? grup(String(n)) : '';
    };

    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.classList) return;

        if (el.classList.contains('input-rupiah')) {
            var panjangLama = el.value.length;
            var pos = el.selectionStart;
            el.value = grup(el.value);
            var posBaru = Math.max(0, (pos || 0) + el.value.length - panjangLama);
            try { el.setSelectionRange(posBaru, posBaru); } catch (err) {}

        } else if (el.classList.contains('input-angka')) {
            var bersih = el.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            if (el.value !== bersih) el.value = bersih;

        } else if (el.classList.contains('input-satuan')) {
            var p = el.selectionStart;
            var baru = hurufAwalBesar(el.value);
            if (el.value !== baru) {
                el.value = baru;
                try { el.setSelectionRange(p, p); } catch (err) {}
            }
        }
    });

    // Kolom stok: saat disentuh, isinya terblok sehingga angka 0 langsung tergantikan
    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (el.classList && el.classList.contains('input-angka')) {
            setTimeout(function () { try { el.select(); } catch (err) {} }, 0);
        }
    });
    // Kolom stok dikosongkan lalu ditinggalkan -> kembali ke 0
    document.addEventListener('focusout', function (e) {
        var el = e.target;
        if (el.classList && el.classList.contains('input-angka') && el.value === '') el.value = '0';
    });
})();