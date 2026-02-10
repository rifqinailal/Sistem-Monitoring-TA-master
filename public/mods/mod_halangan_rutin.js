function tambahData(urlStore) {
    $("#formHalangan")[0].reset();
    $("#modalLabel").text("Tambah Jadwal Rutin");
    $("#original_ids").val("");

    if ($.fn.select2) {
        $("#dosen_id").val("").trigger("change");
        $("#ruangan_id").val("").trigger("change");
    }

    $(".sesi-checkbox").prop("checked", false);

    $("#formHalangan").attr("action", urlStore);

    $("#modalTambah").modal("show");
}

function editData(id, urlShow, urlUpdate) {
    $("#formHalangan")[0].reset();
    $("#modalLabel").text("Edit Jadwal Rutin");

    $("#formHalangan").attr("action", urlUpdate);

    $.ajax({
        url: urlShow,
        type: "GET",
        dataType: "json",
        success: function (response) {
            console.log("Data diterima:", response);

            if ($.fn.select2) {
                if ($("#dosen_id").length) {
                    $("#dosen_id").val(response.dosen_id).trigger("change");
                }
                $("#ruangan_id").val(response.ruangan_id).trigger("change");
            }

            $("#hari").val(response.hari);
            $("#keterangan").val(response.keterangan);

            $("#original_ids").val(response.original_ids);

            $(".sesi-checkbox").prop("checked", false);

            if (response.selected_sesi) {
                var arr = Array.isArray(response.selected_sesi)
                    ? response.selected_sesi
                    : Object.values(response.selected_sesi);

                arr.forEach(function (val) {
                    $(
                        'input.sesi-checkbox[value="' + val.toString() + '"]',
                    ).prop("checked", true);
                });
            }

            $("#modalTambah").modal("show");
        },
        error: function (xhr) {
            console.error(xhr);
            var pesan = "Terjadi kesalahan sistem";
            if (xhr.status == 404)
                pesan = "Data atau URL tidak ditemukan (404)";
            Swal.fire("Gagal", pesan, "error");
        },
    });
}

function hapusHalangan(id, url) {
    Swal.fire({
        title: "Hapus Jadwal Ini?",
        text: "Halangan rutin ini akan dihapus.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Ya, Hapus!",
    }).then((result) => {
        if (result.isConfirmed || result.value) {
            $.ajax({
                url: url,
                type: "GET",
                success: function (data) {
                    Swal.fire("Berhasil!", data.message, "success").then(() => {
                        window.location.reload();
                    });
                },
                error: function (xhr) {
                    var msg = "Gagal menghapus data";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire("Error", msg, "error");
                },
            });
        }
    });
}

$(document).ready(function () {
    if ($.fn.select2) {
        $(".select2").select2({
            dropdownParent: $("#modalTambah"),
            width: "100%",
        });
    }
});
