(function ($) {
  "use strict";
  const checkOrderStatus = () => new Promise((resolve, reject) => {
    $.ajax({
      url: qpay_params.url,
      type: "POST",
    }).done((response) => {
      if (response === 'processing') {
        resolve(true)
      } else {
        resolve(false);
      }
    }).fail(() => {
      reject(false);
    });
  });
  const countdown = (modal, expire = 120) => {
    let startTime = expire;
    let orderCheckInterval;
    const countdownInterval = setInterval(() => {
      if (startTime >= 0) {
        $("#countdown").text(startTime);
        startTime--;
        if (!orderCheckInterval) {
          orderCheckInterval = setInterval( async () => {
            if (startTime > 0) {
              try {
                const response = await checkOrderStatus();
                console.log('response: ', response)
                if (response) {
                  modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                  <div class="row justify-content-center">
                    <img class="payment-result" src="${qpay_params.success}" alt="" />
                    <div class="position-absolute bottom-0">
                      <p class="text-center fs-6 fw-bold text-break">${qpay_params.successText}</p>
                    </div>
                  </div>
                </div>`);
                }
              } catch (error) {
                console.log('xerror:', error)
              }
            } else {
              clearInterval(orderCheckInterval);
              orderCheckInterval = undefined;
              modal.setColumnClass("col-md-6");
                  modal.setContent(`<div class="container">
                  <div class="row justify-content-center">
                    <img class="payment-result" src="${qpay_params.failure}" alt="" />
                    <div class="position-absolute bottom-0">
                      <p class="text-center fs-6 fw-bold text-break">${qpay_params.failureText}</p>
                    </div>
                  </div>
                </div>`);
            }
          }, 3000);
        }
      }
      if (startTime < 0) {
        clearInterval(countdownInterval);
      }
    }, 1000);
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
            <img class="qpay-qrcode" src="${qpay_params.qrcode}" alt="" />
          </div>
        </div>
        <div class="col-md-6">
          <div class="row justify-content-center">
            <p class="text-center"><span id="countdown" class="fs-1 fw-bold">${qpay_params.expire}</span><span> сек</span></p>
            <p class="text-center fs-6 fw-normal text-break">Төлбөр хийгдсэний дараа цонх автоматаар хаагдана. Та төлбөр төлөгдөх хүртэл түр хүлээнэ үү!</p>
          </div>
        </div>
      </div>
    </div>`,
    });
    countdown(modal, qpay_params.expire);
  }
})(jQuery);
