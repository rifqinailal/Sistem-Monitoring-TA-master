function tambahData() {
    $('#modalTambah form')[0].reset();
    $('#modalTambah').modal('show');
}

function editData(id, urlShow) {
    var urlUpdate = `${BASE_URL}/apps/sesi-ujian/update/${id}`;
    $('#formEdit').attr("action", urlUpdate);

    $.ajax({
        url: urlShow,
        type: "GET",
        dataType: "json",
        success: function (response) {

            $('#modalEdit input[name="id"]').val(response.id);
            $('#modalEdit input[name="nama"]').val(response.nama);
            $('#modalEdit input[name="jam_mulai"]').val(response.jam_mulai);
            $('#modalEdit input[name="jam_selesai"]').val(response.jam_selesai);


            if (response.is_active == 1) {
                $('#modalEdit input[name="is_active"]').prop('checked', true);
            } else {
                $('#modalEdit input[name="is_active"]').prop('checked', false);
            }


            $('#modalEdit').modal('show');
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Gagal mengambil data'
            });
        }
    });
}

function hapusSesi(e, url) {
    Swal.fire({
        title: "Hapus Sesi Ujian?",
        text: "Apakah kamu yakin untuk menghapus data ini?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Ya, Hapus!"
    }).then((result) => {
        if (result.isConfirmed || result.value) {
            $.ajax({
                url: url,
                type: "GET",
                success: function (data) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message || 'Data berhasil dihapus'
                    }).then(() => {
                        window.location.reload();
                    });
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan sistem'
                    });
                }
            });
        }
    });
}
