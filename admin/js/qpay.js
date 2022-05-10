(function ($) {
  "use strict";
  const checkOrderStatus = () =>
    new Promise((resolve, reject) => {
      $.ajax({
        url: qpay_params.url,
        type: "POST",
      })
        .done((response) => {
          resolve(response);
        })
        .fail(() => {
          reject(false);
        });
    });
  const countdown = (modal, expire = 120) => {
    let startTime = expire;
    let orderCheckInterval;
    let countdownInterval;
    const stopIntervals = () => {
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = undefined;
      }
      if (orderCheckInterval) {
        clearInterval(orderCheckInterval);
        orderCheckInterval = undefined;
      }
    };
    countdownInterval = setInterval(() => {
      if (startTime >= 0) {
        $("#countdown").text(startTime);
        startTime--;
        if (!orderCheckInterval) {
          orderCheckInterval = setInterval(async () => {
            try {
              const response = await checkOrderStatus();
              switch (response) {
                case "completed":
                  stopIntervals();
                  modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                      <div class="row justify-content-center">
                        <img class="qplugin-payment-result" src="${qpay_params.success}" alt="" />
                        <div class="position-absolute bottom-0">
                          <p class="text-center fs-6 fw-bold text-break">${qpay_params.successText}</p>
                        </div>
                      </div>
                    </div>`);
                  setTimeout(() => {
                    window.location = qpay_params.redirectUrl;
                  }, 4500);
                  break;
                case "processing":
                  stopIntervals();
                  modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                      <div class="row justify-content-center">
                        <img class="qplugin-payment-result" src="${qpay_params.success}" alt="" />
                        <div class="position-absolute bottom-0">
                          <p class="text-center fs-6 fw-bold text-break">${qpay_params.successText}</p>
                        </div>
                      </div>
                    </div>`);
                  setTimeout(() => {
                    window.location = qpay_params.redirectUrl;
                  }, 4500);
                  break;
                case "cancelled":
                  stopIntervals();
                  modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                      <div class="row justify-content-center">
                        <img class="qplugin-payment-result" src="${qpay_params.failure}" alt="" />
                        <div class="position-absolute bottom-0">
                          <p class="text-center fs-6 fw-bold text-break">${qpay_params.cancelledText}</p>
                        </div>
                      </div>
                    </div>`);
                  setTimeout(() => {
                    window.location = qpay_params.orderUrl;
                  }, 4500);
                  break;
                case "failed":
                  stopIntervals();
                  modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                      <div class="row justify-content-center">
                        <img class="qplugin-payment-result" src="${qpay_params.failure}" alt="" />
                        <div class="position-absolute bottom-0">
                          <p class="text-center fs-6 fw-bold text-break">${qpay_params.failedText}</p>
                        </div>
                      </div>
                    </div>`);
                  setTimeout(() => {
                    window.location = qpay_params.orderUrl;
                  }, 4500);
                  break;
                default:
                  break;
              }
            } catch (error) {
              stopIntervals();
              modal.setColumnClass("col-md-6");
              modal.setContent(`<div class="container">
                  <div class="row justify-content-center">
                    <img class="qplugin-payment-result" src="${qpay_params.failure}" alt="" />
                    <div class="position-absolute bottom-0">
                      <p class="text-center fs-6 fw-bold text-break">${qpay_params.serverErrorText}</p>
                    </div>
                  </div>
                </div>`);
            }
          }, 3000);
        }
      } else {
        stopIntervals();
        modal.setColumnClass("col-md-6");
        modal.setContent(`<div class="container">
            <div class="row justify-content-center">
              <img class="qplugin-payment-result" src="${qpay_params.failure}" alt="" />
              <div class="position-absolute bottom-0">
                <p class="text-center fs-6 fw-bold text-break">${qpay_params.expiredText}</p>
              </div>
            </div>
          </div>`);
      }
    }, 1000);
  };

  const deepLink = () => {
    if (
      /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
        navigator.userAgent
      )
    ) {
      return `<div class="row justify-content-center">
          <div class="col col-md-auto">
            <a href="${qpay_params.deeplink}" class="btn btn-light qplugin-deep-link" role="button" aria-pressed="true" target="_blank">${qpay_params.deeplinkText}</a>
          </div>
        </div>`;
    }
    return ``;
  };

  if (qpay_params) {
    const modal = $.dialog({
      title: `<div class="row justify-content-center align-items-center ">
      <img src="${qpay_params.icon}" width="77" height="34" alt="qpay" />
    </div>`,
      useBootstrap: true,
      draggable: false,
      columnClass: "col-md-8",
      content: `<div class="container">
          <div class="row align-items-center">
            <div class="col-md-6">
              <div class="row justify-content-center">
                <img class="qplugin-qrcode" src="${
                  qpay_params.qrcode
                }" alt="" />
              </div>
            </div>
            <div class="col-md-6">
              <div class="row justify-content-center">
                <p class="text-center"><span id="countdown" class="fs-1 fw-bold">${
                  qpay_params.expire
                }</span><span> сек</span></p>
                <p class="text-center fs-6 fw-normal text-break">${
                  qpay_params.processingText
                }</p>
                ${deepLink()}
              </div>
            </div>
          </div>
        </div>`,
    });
    countdown(modal, qpay_params.expire);
  }
})(jQuery);
