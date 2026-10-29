window.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('#accordionSidebar');
    var toggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');

    function setToggled(on) {
        if (!sidebar) {
            return;
        }
        document.body.classList.toggle('sidebar-toggled', on);
        sidebar.classList.toggle('toggled', on);
        document.body.classList.toggle('sidebar-open', !on && window.innerWidth < 768);
    }

    if (window.innerWidth < 768) {
        setToggled(true);
    } else if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        setToggled(true);
    }

    function isMobile() {
        return window.innerWidth < 768;
    }

    toggles.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!sidebar) {
                return;
            }
            var on = !sidebar.classList.contains('toggled');
            setToggled(on);
            if (!isMobile()) {
                localStorage.setItem('sb|sidebar-toggle', on ? 'true' : 'false');
            }
        });
    });

    var overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', function () {
            setToggled(true);
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll('a.nav-link, a.sidebar-brand').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    setToggled(true);
                }
            });
        });
    }

    var wasMobile = isMobile();
    window.addEventListener('resize', function () {
        var mobile = isMobile();
        if (mobile === wasMobile) {
            return;
        }
        wasMobile = mobile;
        if (mobile) {
            setToggled(true);
        } else {
            setToggled(localStorage.getItem('sb|sidebar-toggle') === 'true');
        }
    });
});

document.querySelectorAll('.tag-select').forEach(function (root) {
    var max = parseInt(root.getAttribute('data-max') || '10', 10);
    var name = root.getAttribute('data-name') || 'id_trainer[]';
    var control = root.querySelector('.tag-select-control');
    var values = root.querySelector('.tag-select-values');
    var placeholder = root.querySelector('.tag-select-placeholder');
    var menu = root.querySelector('.tag-select-menu');
    var search = root.querySelector('.tag-select-search');
    var options = Array.prototype.slice.call(root.querySelectorAll('.tag-select-option'));
    var empty = root.querySelector('.tag-select-empty');
    var query = '';
    var selected = options.filter(function (option) {
        return option.getAttribute('data-selected') === '1';
    }).map(function (option) {
        return option.getAttribute('data-value');
    });

    function cocok(option) {
        if (selected.indexOf(option.getAttribute('data-value')) !== -1) {
            return false;
        }
        if (query === '') {
            return true;
        }
        return option.textContent.trim().toLowerCase().indexOf(query) !== -1;
    }

    function render() {
        values.textContent = '';
        root.querySelectorAll('input[data-tag-input]').forEach(function (input) {
            input.remove();
        });
        selected.forEach(function (id) {
            var option = options.find(function (item) {
                return item.getAttribute('data-value') === id;
            });
            if (!option) {
                return;
            }
            var chip = document.createElement('span');
            chip.className = 'tag-chip';
            var label = document.createElement('span');
            label.textContent = option.textContent.trim();
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'tag-chip-remove';
            remove.setAttribute('aria-label', 'Hapus ' + label.textContent);
            remove.textContent = '×';
            remove.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                selected = selected.filter(function (item) {
                    return item !== id;
                });
                render();
            });
            chip.appendChild(label);
            chip.appendChild(remove);
            values.appendChild(chip);

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = id;
            input.setAttribute('data-tag-input', '1');
            root.appendChild(input);
        });
        placeholder.hidden = selected.length > 0;
        var ada = false;
        options.forEach(function (option) {
            var tampil = cocok(option);
            option.hidden = !tampil;
            if (tampil) {
                ada = true;
            }
        });
        if (empty) {
            empty.hidden = ada;
            empty.textContent = query === '' ? 'Semua peserta sudah dipilih.' : 'Tidak ditemukan.';
        }
    }

    function setOpen(open) {
        root.classList.toggle('is-open', open);
        menu.hidden = !open;
        control.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && search) {
            search.focus();
        }
    }

    control.addEventListener('click', function () {
        setOpen(menu.hidden);
    });

    if (search) {
        search.addEventListener('input', function () {
            query = search.value.trim().toLowerCase();
            render();
        });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                var pertama = options.find(function (option) {
                    return !option.hidden;
                });
                if (pertama) {
                    pertama.click();
                }
            }
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });
    }

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            var id = option.getAttribute('data-value');
            if (selected.indexOf(id) !== -1) {
                return;
            }
            if (selected.length >= max) {
                window.alert('Nama trainer maksimal ' + max + ' orang.');
                return;
            }
            selected.push(id);
            if (search) {
                search.value = '';
                query = '';
            }
            render();
            if (search) {
                search.focus();
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    var form = root.closest('form');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (selected.length > 0) {
                return;
            }
            event.preventDefault();
            setOpen(true);
            control.focus();
        });
    }

    render();
});

document.querySelectorAll('select.form-select').forEach(function (select) {
    if (select.disabled || select.multiple || select.classList.contains('select-polos') || select.closest('.search-select')) {
        return;
    }

    var opsi = Array.prototype.slice.call(select.options).filter(function (option) {
        return option.value !== '';
    });
    var kosong = Array.prototype.slice.call(select.options).find(function (option) {
        return option.value === '';
    });
    var teksKosong = kosong ? kosong.textContent.trim() : 'Pilih';

    var bungkus = document.createElement('div');
    bungkus.className = 'search-select';
    select.parentNode.insertBefore(bungkus, select);
    bungkus.appendChild(select);
    select.classList.add('search-select-native');
    select.tabIndex = -1;

    var tombol = document.createElement('button');
    tombol.type = 'button';
    tombol.className = 'search-select-control';
    tombol.setAttribute('aria-haspopup', 'listbox');
    tombol.setAttribute('aria-expanded', 'false');
    var label = document.createElement('span');
    label.className = 'search-select-label';
    var caret = document.createElement('i');
    caret.className = 'fas fa-chevron-down search-select-caret';
    caret.setAttribute('aria-hidden', 'true');
    tombol.appendChild(label);
    tombol.appendChild(caret);

    var menu = document.createElement('div');
    menu.className = 'search-select-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    var cari = document.createElement('input');
    cari.type = 'search';
    cari.className = 'form-control form-control-sm search-select-search';
    cari.placeholder = 'Cari...';
    cari.setAttribute('aria-label', 'Cari');
    var daftar = document.createElement('div');
    var pesan = document.createElement('div');
    pesan.className = 'tag-select-empty';
    pesan.hidden = true;
    pesan.textContent = 'Tidak ditemukan.';
    menu.appendChild(cari);
    menu.appendChild(daftar);
    menu.appendChild(pesan);
    bungkus.appendChild(tombol);
    bungkus.appendChild(menu);

    var item = opsi.map(function (option) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tag-select-option';
        btn.textContent = option.textContent.trim();
        btn.setAttribute('data-value', option.value);
        btn.addEventListener('click', function () {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            gambar();
            tutup();
        });
        daftar.appendChild(btn);
        return btn;
    });

    function gambar() {
        var terpilih = Array.prototype.slice.call(select.options).find(function (option) {
            return option.value === select.value && option.value !== '';
        });
        label.textContent = terpilih ? terpilih.textContent.trim() : teksKosong;
        label.classList.toggle('is-placeholder', !terpilih);
        if (select.value !== '') {
            tombol.classList.remove('is-invalid');
        }
    }

    function saring() {
        var kata = cari.value.trim().toLowerCase();
        var ada = false;
        item.forEach(function (btn) {
            var cocok = kata === '' || btn.textContent.trim().toLowerCase().indexOf(kata) !== -1;
            btn.hidden = !cocok;
            if (cocok) {
                ada = true;
            }
        });
        pesan.hidden = ada;
    }

    function buka(ya) {
        bungkus.classList.toggle('is-open', ya);
        menu.hidden = !ya;
        tombol.setAttribute('aria-expanded', ya ? 'true' : 'false');
        if (ya) {
            cari.focus();
            cari.select();
        }
    }

    function tutup() {
        buka(false);
        cari.value = '';
        saring();
    }

    tombol.addEventListener('click', function () {
        buka(menu.hidden);
    });
    cari.addEventListener('input', saring);
    cari.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            var pertama = item.find(function (btn) {
                return !btn.hidden;
            });
            if (pertama) {
                pertama.click();
            }
        }
        if (event.key === 'Escape') {
            tutup();
        }
    });
    select.addEventListener('focus', function () {
        buka(true);
    });
    select.addEventListener('invalid', function () {
        tombol.classList.add('is-invalid');
    });
    document.addEventListener('click', function (event) {
        if (!bungkus.contains(event.target)) {
            tutup();
        }
    });

    gambar();
});

document.querySelectorAll('.kamera-box').forEach(function (box) {
    var video = box.querySelector('.kamera-video');
    var canvas = box.querySelector('.kamera-canvas');
    var hasil = box.querySelector('.kamera-hasil');
    var kosong = box.querySelector('.kamera-kosong');
    var buka = box.querySelector('[data-kamera="buka"]');
    var jepret = box.querySelector('[data-kamera="jepret"]');
    var ulang = box.querySelector('[data-kamera="ulang"]');
    var error = box.querySelector('.kamera-error');
    var input = box.querySelector('input[type="file"]');
    var stream = null;

    function stop() {
        if (!stream) {
            return;
        }
        stream.getTracks().forEach(function (track) {
            track.stop();
        });
        stream = null;
        if (video) {
            video.srcObject = null;
        }
    }

    function tampilHasil(src) {
        hasil.src = src;
        hasil.hidden = false;
        video.hidden = true;
        kosong.hidden = true;
        buka.hidden = true;
        jepret.hidden = true;
        ulang.hidden = false;
    }

    function mulai() {
        error.hidden = true;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            error.textContent = 'Kamera tidak tersedia di browser ini.';
            error.hidden = false;
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .catch(function () {
                return navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            })
            .then(function (media) {
                stream = media;
                video.srcObject = media;
                video.hidden = false;
                hasil.hidden = true;
                kosong.hidden = true;
                buka.hidden = true;
                jepret.hidden = false;
                ulang.hidden = true;
            })
            .catch(function () {
                error.textContent = 'Tidak dapat membuka kamera. Izinkan akses kamera pada browser.';
                error.hidden = false;
            });
    }

    buka.addEventListener('click', mulai);
    ulang.addEventListener('click', function () {
        input.value = '';
        mulai();
    });
    jepret.addEventListener('click', function () {
        if (!video.videoWidth) {
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
            if (!blob) {
                return;
            }
            var file = new File([blob], 'absensi.jpg', { type: 'image/jpeg' });
            var data = new DataTransfer();
            data.items.add(file);
            input.files = data.files;
            tampilHasil(canvas.toDataURL('image/jpeg', 0.9));
            stop();
        }, 'image/jpeg', 0.9);
    });

    var modal = box.closest('.modal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', stop);
    }
});

document.addEventListener('click', function (event) {
    var salin = event.target.closest('[data-copy-target]');
    if (!salin) {
        return;
    }
    var sumber = document.getElementById(salin.getAttribute('data-copy-target') || '');
    if (!sumber) {
        return;
    }
    var teks = sumber.value || sumber.textContent || '';
    var label = salin.querySelector('span');
    var awal = label ? label.textContent : salin.textContent;
    function berhasil() {
        if (label) {
            label.textContent = 'Tersalin';
            window.setTimeout(function () {
                label.textContent = awal;
            }, 1600);
        }
    }
    function cadangan() {
        sumber.classList.remove('laporan-wa-sumber');
        sumber.focus();
        sumber.select();
        document.execCommand('copy');
        sumber.classList.add('laporan-wa-sumber');
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(teks).then(berhasil).catch(function () {
            cadangan();
            berhasil();
        });
    } else {
        cadangan();
        berhasil();
    }
});

document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-preview]');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    var modalEl = document.getElementById('modalPreview');
    var img = document.getElementById('previewImage');
    var caption = document.getElementById('previewCaption');
    if (!modalEl || !img || typeof bootstrap === 'undefined') {
        return;
    }
    img.src = trigger.getAttribute('data-preview') || '';
    img.alt = trigger.getAttribute('data-caption') || 'Dokumentasi';
    if (caption) {
        caption.textContent = trigger.getAttribute('data-caption') || '';
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
