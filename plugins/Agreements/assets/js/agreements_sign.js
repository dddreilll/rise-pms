(function ($) {
    "use strict";

    var cfg = window.AgreementsSign || {};
    var mainPad = null;
    var pdfDoc = null;
    var pageNum = 1;
    var scale = 1.1;

    function initPad() {
        var mainCanvas = document.getElementById("main-signature-pad");
        if (mainCanvas && window.SignaturePad) {
            mainPad = new SignaturePad(mainCanvas);
        }
    }

    function renderPage(num) {
        if (!pdfDoc) return;
        pdfDoc.getPage(num).then(function (page) {
            var viewport = page.getViewport({ scale: scale });
            var canvas = document.getElementById("sign-pdf-canvas");
            var ctx = canvas.getContext("2d");
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            page.render({ canvasContext: ctx, viewport: viewport });
            $("#sign-page-num").text(num);
        });
    }

    $(function () {
        initPad();

        $("#clear-main-sig").on("click", function () {
            if (mainPad) mainPad.clear();
        });

        $("#decline-btn").on("click", function () {
            $("#decline-box").removeClass("hide");
        });

        $("#confirm-decline").on("click", function () {
            $.ajax({
                url: cfg.declineUrl,
                type: "POST",
                dataType: "json",
                data: { decline_reason: $("#decline_reason").val() },
                success: function (result) {
                    $("#sign-message").html("<div class='alert alert-warning'>" + result.message + "</div>");
                    $("#sign-form").find("button").prop("disabled", true);
                }
            });
        });

        $("#sign-form").on("submit", function (e) {
            e.preventDefault();
            if (!mainPad || mainPad.isEmpty()) {
                $("#sign-message").html("<div class='alert alert-danger'>Signature is required.</div>");
                return;
            }
            $("#signature_data").val(mainPad.toDataURL());
            $.ajax({
                url: cfg.submitUrl,
                type: "POST",
                dataType: "json",
                data: $(this).serialize(),
                success: function (result) {
                    if (result.success) {
                        $("#sign-message").html("<div class='alert alert-success'>" + result.message + "</div>");
                        $("#sign-form").find("button").prop("disabled", true);
                    } else {
                        $("#sign-message").html("<div class='alert alert-danger'>" + result.message + "</div>");
                    }
                }
            });
        });

        $("#sign-prev-page").on("click", function () {
            if (pageNum <= 1) return;
            pageNum--;
            renderPage(pageNum);
        });
        $("#sign-next-page").on("click", function () {
            if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
            pageNum++;
            renderPage(pageNum);
        });

        if (cfg.documentType === "pdf" && cfg.pdfUrl && window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
            pdfjsLib.getDocument(cfg.pdfUrl).promise.then(function (doc) {
                pdfDoc = doc;
                renderPage(pageNum);
            });
        }
    });
})(jQuery);
