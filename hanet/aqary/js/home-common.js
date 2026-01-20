function blockError(e) {
  try {
    console.log(e);
  } catch (err) {}
  return true;
}
//window.onerror = blockError;

function checkdotnetobj() {
  try {
    if (window.external.isdotnet()) {
      return;
    }
  } catch (err) {
    document.getElementById("lunc").classid =
      "clsid:9910BEE9-58ED-4636-A852-DF89C76A9384";
    document.getElementById("lunc").CODEBASE = "\\aqary\\lunc.cab";
  }
}

checkdotnetobj();

//###################ToolTip

function showToolTip(e, text) {
  if (document.all) e = event;

  var obj = document.getElementById("bubble_tooltip");
  var obj2 = document.getElementById("bubble_tooltip_content");
  obj2.innerHTML = text;
  obj.style.display = "block";
  var st = Math.max(
    document.body.scrollTop,
    document.documentElement.scrollTop
  );
  if (navigator.userAgent.toLowerCase().indexOf("safari") >= 0) st = 0;
  var leftPos = e.clientX - 100;
  if (leftPos < 0) leftPos = 0;
  obj.style.left = leftPos + "px";
  obj.style.top = e.clientY - obj.offsetHeight - 1 + st + "px";
}

function hideToolTip() {
  document.getElementById("bubble_tooltip").style.display = "none";
}

//####################end ToolTip

function checkdateformat(vDateobj, vDateValue) {
  if (vDateValue == "") {
    return false;
  }
  vDateValue = vDateValue.replace(/-/gi, "/");
  vDateValue = vDateValue.replace(/\./gi, "/");
  vDateValue = vDateValue.replace(/\\/gi, "/");
  vDateValue = vDateValue.split("/");
  err = 0;
  intday = parseInt(vDateValue[0], 10);
  if (isNaN(intday)) {
    err = 1;
  }
  if (intday < 1 || intday > 31) {
    err = 1;
  }

  intmonth = parseInt(vDateValue[1], 10);
  if (isNaN(intmonth)) {
    err = 2;
  }
  if (intmonth < 1 || intmonth > 12) {
    err = 1;
  }

  intyear = parseInt(vDateValue[2], 10);
  if (isNaN(intyear)) {
    err = 3;
  }
  if (intyear < 1300 || intyear > 2200) {
    err = 3;
  }

  if (err == 0) {
    vDateobj.value = intday + "/" + intmonth + "/" + intyear;
    vDateobj.style.background = "#ffffff";
    return true;
  } else {
    //msgboxme('التاريخ المدخل غير صحيح ','خطأ','16');
    vDateobj.style.background = "#ff0000";
    vDateobj.title = "يجب إدخال التاريخ بشكل صحيح مثال : 1/12/1410";
    vDateobj.focus();
    return false;
  }

  return false;
}

function shellme(exefile, exepram) {
  if (typeof exepram == "undefined") {
    exepram = "";
  }
  try {
    if (window.external.isdotnet()) {
      window.external.shellme(exefile, exepram);
      return;
    }
  } catch (err) {}

  if (typeof document.getElementById("lunc") != "undefined") {
    document.getElementById("lunc").shellme(exefile, exepram);
  }
}

function msgboxme(boxmssage, boxtitle, boxtype) {
  if (typeof boxtitle == "undefined") {
    boxtitle = " ";
  }
  if (typeof boxtype == "undefined") {
    boxtype = 1;
  }
  alert(boxmssage);
  return;

  try {
    if (window.external.isdotnet()) {
      return window.external.msgboxme(boxmssage, boxtitle, boxtype);
    }
  } catch (err) {}

  if (typeof document.getElementById("lunc") != "undefined") {
    boxresult = document
      .getElementById("lunc")
      .msgboxme(boxtitle, boxmssage, boxtype);
    return boxresult;
  }
}

function openvbs(url, username, password) {
  try {
    url = url + "&username=" + username + "&password=" + password;
    if (window.external.isdotnet()) {
      showProgress();
      setTimeout("hideProgress()", 40000);
      return window.external.openvbs(url, username, password);
    }
  } catch (err) {}

  wordd.location.href = url;
  return boxresult;
}

function ValidateNumberKeyPress(field, evt) {
  var charCode = evt.which ? evt.which : event.keyCode;
  var keychar = String.fromCharCode(charCode);

  if (
    charCode > 31 &&
    (charCode < 48 || charCode > 57) &&
    keychar != "." &&
    keychar != "-"
  ) {
    return false;
  }

  if (keychar == "." && field.value.indexOf(".") != -1) {
    return false;
  }

  if (keychar == "-") {
    if (field.value.indexOf("-") != -1 /* || field.value[0] == "-" */) {
      return false;
    } else {
      //save caret position
      var caretPos = getCaretPosition(field);
      if (caretPos != 0) {
        return false;
      }
    }
  }

  return true;
}

function ValidateNumberKeyUp(field) {
  if (document.selection == undefined) {
    return;
  }

  if (document.selection.type == "Text") {
    return;
  }

  //save caret position
  var caretPos = getCaretPosition(field);

  var fdlen = field.value.length;

  UnFormatNumber(field);

  var IsFound = /^-?\d+\.{0,1}\d*$/.test(field.value);
  if (!IsFound) {
    setSelectionRange(field, caretPos, caretPos);
    return false;
  }

  field.value = FormatNumber(field.value);

  fdlen = field.value.length - fdlen;

  setSelectionRange(field, caretPos + fdlen, caretPos + fdlen);
}

function ValidateAndFormatNumber(NumberTextBox) {
  if (NumberTextBox.value == "") return;

  UnFormatNumber(NumberTextBox);

  var IsFound = /^-?\d+\.{0,1}\d*$/.test(NumberTextBox.value);
  if (!IsFound) {
    //alert("Not a number");
    //NumberTextBox.focus();
    //NumberTextBox.select();
    return;
  }

  if (isNaN(parseFloat(NumberTextBox.value))) {
    //alert("Number exceeding float range");
    //NumberTextBox.focus();
    //NumberTextBox.select();
    return;
  }

  NumberTextBox.value = FormatNumber(NumberTextBox.value);
}

function FormatNumber(fnum) {
  var orgfnum = fnum;
  var flagneg = false;

  if (fnum.charAt(0) == "-") {
    flagneg = true;
    fnum = fnum.substr(1, fnum.length - 1);
  }

  psplit = fnum.split(".");

  var cnum = psplit[0],
    parr = [],
    j = cnum.length,
    m = Math.floor(j / 3),
    n = cnum.length % 3 || 3;

  // break the number into chunks of 3 digits; first chunk may be less than 3
  for (var i = 0; i < j; i += n) {
    if (i != 0) {
      n = 3;
    }
    parr[parr.length] = cnum.substr(i, n);
    m -= 1;
  }

  // put chunks back together, separated by comma
  fnum = parr.join(",");

  // add the precision back in
  //if (psplit[1]) {fnum += "." + psplit[1];}
  if (orgfnum.indexOf(".") != -1) {
    fnum += "." + psplit[1];
  }

  if (flagneg == true) {
    fnum = "-" + fnum;
  }

  return fnum;
}

function UnFormatNumber(obj) {
  if (obj.value == "") return;

  obj.value = obj.value.replace(/,/gi, "");
}

function getCaretPosition(objTextBox) {
  var objTextBox = window.event.srcElement;

  var i = objTextBox.value.length;

  if (objTextBox.createTextRange) {
    objCaret = document.selection.createRange().duplicate();
    while (
      objCaret.parentElement() == objTextBox &&
      objCaret.move("character", 1) == 1
    )
      --i;
  }
  return i;
}

function setSelectionRange(input, selectionStart, selectionEnd) {
  if (input.setSelectionRange) {
    input.focus();
    input.setSelectionRange(selectionStart, selectionEnd);
  } else if (input.createTextRange) {
    var range = input.createTextRange();
    range.collapse(true);
    range.moveEnd("character", selectionEnd);
    range.moveStart("character", selectionStart);
    range.select();
  }
}

function DL_GetElementLeft(eElement) {
  var nLeftPos = eElement.offsetLeft; // initialize var to store calculations
  var eParElement = eElement.offsetParent; // identify first offset parent element
  while (eParElement != null) {
    // move up through element hierarchy
    nLeftPos += eParElement.offsetLeft; // appending left offset of each parent
    eParElement = eParElement.offsetParent; // until no more offset parents exist
  }
  return nLeftPos; // return the number calculated
}

function DL_GetElementTop(eElement) {
  var nTopPos = eElement.offsetTop; // initialize var to store calculations
  var eParElement = eElement.offsetParent; // identify first offset parent element
  while (eParElement != null) {
    // move up through element hierarchy
    nTopPos += eParElement.offsetTop; // appending top offset of each parent
    eParElement = eParElement.offsetParent; // until no more offset parents exist
  }
  return nTopPos; // return the number calculated
}

function createCookie(name, value, days) {
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    var expires = "; expires=" + date.toGMTString();
  } else var expires = "";
  document.cookie = name + "=" + value + expires + "; path=/";
}

function readCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) == " ") c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
  }
  return "";
}

function eraseCookie(name) {
  createCookie(name, "", -1);
}

function openorclose(x) {
  if (readCookie(x) == "block") {
    createCookie(x, "none", "100");
    document.getElementById(x).style.display = "none";
    return false;
  } else if (readCookie(x) == "none") {
    createCookie(x, "block", "100");
    document.getElementById(x).style.display = "block";
    return false;
  } else {
    createCookie(x, "none", "100");
    document.getElementById(x).style.display = "none";
    return false;
  }
}

function opennewurl(x) {
  if (document.getElementById("rnumber2").value == "_blank") {
    window.open(x);
  } else {
    window.location.href = x;
  }
  return false;
}

function printURL(sHref) {
  if (document.getElementById && document.all && sHref) {
    if (!self.oPrintElm) {
      var aHeads = document.getElementsByTagName("HEAD");
      if (!aHeads || !aHeads.length) return false;
      if (!self.oPrintElm) self.oPrintElm = document.createElement("LINK");
      self.oPrintElm.rel = "alternate";
      self.oPrintElm.media = "print";
      aHeads[0].appendChild(self.oPrintElm);
    }
    self.oPrintElm.href = sHref;
    self.focus();
    self.print();
    return true;
  } else return false;
}

if (document.all) {
  document.onkeydown = function () {
    var key_f5 = 116;

    if (key_f5 == event.keyCode) {
      event.keyCode = 0;
      return false;
    }
  };
}

function fadeOut(oDiv) {
  oDiv.style.filter = "blendTrans(duration=2)";
  // Make sure the filter is not playing.
  if (oDiv.filters.blendTrans.status != 2) {
    oDiv.filters.blendTrans.apply();
    oDiv.style.visibility = "hidden";
    oDiv.filters.blendTrans.play();
  }
}

function fadeIn(oDiv) {
  oDiv.style.filter = "blendTrans(duration=2)";
  // Make sure the filter is not playing.
  if (oDiv.filters.blendTrans.status != 2) {
    oDiv.filters.blendTrans.apply();
    oDiv.style.visibility = "visible";
    oDiv.filters.blendTrans.play();
  }
}

function showmsgbox(obj, href, optionaltop, optionalleft) {
  if (typeof optionaltop == "undefined") {
    optionaltop = "";
  }
  if (typeof optionalleft == "undefined") {
    optionalleft = "";
  }

  //fadeIn(document.getElementById('tooltipf'));
  document.getElementById("tooltipf").src = href;
  document.getElementById("tooltipf").style.display = "block";
  document.getElementById("tooltipf").style.top = DL_GetElementTop(obj) + "px";
  document.getElementById("tooltipf").style.left =
    DL_GetElementLeft(obj) - document.getElementById("tooltipf").Width + "px";

  if (optionaltop > 0) {
    document.getElementById("tooltipf").style.top = optionaltop + "px";
    document.getElementById("tooltipf").style.left = optionalleft + "px";
  }

  return false;
}

function showlayerbox(x, okcancel) {
  var result = false;
  if (typeof okcancel == "undefined") {
    okcancel = 0;
  }
  $("#layerboxx").html(
    "<img style='cursor:hand;position: absolute;right:1px;top:1px;' id='showlayerboxCloseImage' " +
      " src='/aqary/ext/resources/images/vista/basic-dialog/close.gif' border=0 " +
      " onxclick=this.parentNode.style.display='none'; alt='اغلاق ' > " +
      x +
      "<br><button  TYPE=button class='button blue uifont16' id='showlayerboxClose'  " +
      " oxnclick=this.parentNode.style.display='none'; " +
      " style='position: absolute;left:10px;bottom:10px;'  >موافق</button>"
  );
  if (okcancel == 1) {
    $("#layerboxx").html(
      $("#layerboxx").html() +
        "<button  TYPE=button class='button red uifont16' id='showlayerboxCancel'  " +
        " oxnclick=this.parentNode.style.display='none'; " +
        " style='position: absolute;left:100px;bottom:10px;'  >الغاء</button>"
    );
  }
  $("#layerboxx").show();

  $("#showlayerboxCancel").click(function () {
    $("#layerboxx").hide();
    result = 0;
  });
  $("#showlayerboxCloseImage").click(function () {
    $("#layerboxx").hide();
    result = 1;
  });
  $("#showlayerboxClose").click(function () {
    $("#layerboxx").hide();
    result = 1;
  });
  return result;
}

function intval(xdata) {
  var xp = (xdata + "").replace(/,/g, "");
  xp = parseInt(xp, 10);
  if (isNaN(xp)) {
    xp = 0;
  }
  return xp;
}

function floatval(xdata) {
  //console.log("input" + xdata);
  var xp = (xdata + "").replace(/,/g, "");
  xp = parseFloat(xp).toFixed(2);
  xp = parseFloat(xp);
  if (isNaN(xp) || xp == null) {
    xp = 0;
  }
  //console.log("vale:" + xp);
  return xp;
}

//show loading in the page ###########
var spinnerVisible = false;

function showProgress() {
  if (!spinnerVisible) {
    $("div#spinner").fadeIn("fast");
    spinnerVisible = true;
  }
}

function hideProgress() {
  if (spinnerVisible) {
    var spinner = $("div#spinner");
    spinner.stop();
    spinner.fadeOut("fast");
    spinnerVisible = false;
  }
}

// Detect if the browser is IE or not.
// If it is not IE, we assume that the browser is NS.
var IE = document.all ? true : false;

// If NS -- that is, !IE -- then set up for mouse capture
if (!IE) document.captureEvents(Event.MOUSEMOVE);

// Set-up to use getMouseXY function onMouseMove
//document.onmousemove = getMouseXY;

// Temporary variables to hold mouse x-y pos.s
var tempX = 0;
var tempY = 0;

// Main function to retrieve mouse x-y pos.s

function getMouseXY(e) {
  if (IE) {
    // grab the x-y pos.s if browser is IE
    tempX = event.clientX + document.body.scrollLeft;
    tempY = event.clientY + document.body.scrollTop;
  } else {
    // grab the x-y pos.s if browser is NS
    tempX = e.pageX;
    tempY = e.pageY;
  }
  // catch possible negative values in NS4
  if (tempX < 0) {
    tempX = 0;
  }
  if (tempY < 0) {
    tempY = 0;
  }

  return true;
}

var currentMousePos = {
  x: -1,
  y: -1,
};

jQuery(function ($) {
  $(document).mousemove(function (event) {
    currentMousePos.x = event.pageX;
    currentMousePos.y = event.pageY;
  });

  // ELSEWHERE, your code that needs to know the mouse position without an event
  if (currentMousePos.x < 10) {
    // ....
  }
});

function getMouseX(e) {
  $(document).click(function (e) {
    //$('#status').html(e.pageX +', '+ e.pageY);
    return e.pageX;
  });
  if (IE) {
    // grab the x-y pos.s if browser is IE
    tempX = event.clientX + document.body.scrollLeft;
  } else {
    // grab the x-y pos.s if browser is NS
    tempX = e.pageX;
  }
  // catch possible negative values in NS4
  if (tempX < 0) {
    tempX = 0;
  }
  return tempX;
}

function getMouseY(e) {
  $(document).click(function (e) {
    //$('#status').html(e.pageX +', '+ e.pageY);
    return e.pageY;
  });

  if (IE) {
    // grab the x-y pos.s if browser is IE
    tempY = event.clientY + document.body.scrollTop;
  } else {
    // grab the x-y pos.s if browser is NS
    tempY = e.pageY;
  }
  // catch possible negative values in NS4
  if (tempY < 0) {
    tempY = 0;
  }
  return tempY;
}

function movepice(x) {
  document.getElementById(x).style.left = getMouseX() - 24;
  document.getElementById(x).style.top = getMouseY() - 24;
}

function deletetitle(id) {
  var row = document.getElementById(id).getElementsByTagName("TR")[0];
  row.deleteCell();
}

function settitle(id, c1) {
  return;
}

function gotorefere() {
  window.location.href = "<?= $_SERVER[HTTP_REFERER] ?>";
}

function activateselect(tag, x, masterElement) {
  if (!window.event) return;
  var source = event.srcElement;
  while (source != masterElement && source.tagName != "HTML") {
    if (source.tagName == tag.toUpperCase()) {
      cc.xcd.value = x;
      break;
    }
    source = source.parentElement;
  }
}

// Patch window.print with IIFE to capture native function in closure
(function() {
  // Only run once
  if (window.__print_patched) {
    return;
  }
  window.__print_patched = true;

  // Capture the REAL native print function in a closure variable
  var nativePrint = window.print;

  $(document).ready(function () {
    window.print = function () {
      try {
        var OLECMDID = 7;
        /* OLECMDID values:
         * 6 - print
         * 7 - print preview
         * 1 - open window
         * 4 - Save As
         */
        var PROMPT = 1; // 2 DONTPROMPTUSER
        var WebBrowser =
          '<OBJECT ID="WebBrowser1"  name="WebBrowser1" WIDTH=0 HEIGHT=0 CLASSID="CLSID:8856F961-340A-11D0-A96B-00C04FD705A2"></OBJECT>';
        document.body.insertAdjacentHTML("beforeEnd", WebBrowser);
        WebBrowser1.ExecWB(OLECMDID, PROMPT);
        WebBrowser1.outerHTML = "";
      } catch (err) {
        // Call the native print function from closure
        nativePrint.call(window);
      }
    };
  });
})();

// ============================================
// Bootstrap 5 Modal Alert and Confirm Overrides
// ============================================

window.old_alert = window.alert;
window.old_confirm = window.confirm;

/**
 * Bootstrap 5 Alert Modal
 * Replaces the native alert() function with a modern Bootstrap 5 modal
 *
 * @param {string} message - The message to display
 * @param {string} title - Optional title (default: "رسالة")
 * @param {function|string} callback - Optional callback function or URL to redirect to
 */
function alert(message, title, callback) {
  if (typeof title == "undefined" || title === false || title === null) {
    title = "رسالة";
  }
  if (typeof callback == "undefined") {
    callback = null;
  }

  // Check if Bootstrap modal is available
  if (
    typeof bootstrap === "undefined" ||
    !document.getElementById("globalAlertModal")
  ) {
    // Fallback to native alert if Bootstrap is not loaded
    old_alert(message);
    if (callback && typeof callback === "function") {
      callback();
    }
    return;
  }

  const modalElement = document.getElementById("globalAlertModal");
  const modalTitle = document.getElementById("globalAlertTitle");
  const modalMessage = document.getElementById("globalAlertMessage");
  const modalOkBtn = document.getElementById("globalAlertOkBtn");

  modalTitle.innerHTML = '<i class="bi bi-info-circle-fill me-2"></i>' + title;
  modalMessage.innerHTML = message;

  const modal = new bootstrap.Modal(modalElement);

  // Remove previous event listeners
  const newBtn = modalOkBtn.cloneNode(true);
  modalOkBtn.parentNode.replaceChild(newBtn, modalOkBtn);

  // Add new event listener
  document.getElementById("globalAlertOkBtn").onclick = function () {
    modal.hide();
    if (callback) {
      if (typeof callback === "string") {
        window.location.href = callback;
      } else if (typeof callback === "function") {
        callback();
      }
    }
  };

  // Check if other modals are already open and set z-index before showing
  const openModals = document.querySelectorAll('.modal.show');
  if (openModals.length > 0) {
    // Other modals are open, set higher z-index
    modalElement.style.cssText = 'z-index: 9999 !important;';
  }

  modal.show();

  // After modal is shown, ensure backdrop also has correct z-index
  setTimeout(function() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    if (backdrops.length > 1 && openModals.length > 0) {
      // Set the last backdrop (belongs to alert) to be just below the alert modal
      backdrops[backdrops.length - 1].style.cssText = 'z-index: 9998 !important;';
    }
  }, 100);
}

/**
 * Bootstrap 5 Confirm Modal
 * Replaces the native confirm() function with a modern Bootstrap 5 modal
 *
 * @param {string} message - The message to display
 * @param {string} title - Optional title (default: "تأكيد")
 * @param {function|string} callback - Callback function or URL to execute on confirm
 * @param {function} cancelCallback - Optional callback function to execute on cancel
 */
function confirm(message, title, callback, cancelCallback) {
  if (typeof title == "undefined" || title === null) {
    title = "تأكيد";
  }
  if (typeof callback == "undefined") {
    callback = null;
  }
  if (typeof cancelCallback == "undefined") {
    cancelCallback = null;
  }

  // Check if Bootstrap modal is available
  if (
    typeof bootstrap === "undefined" ||
    !document.getElementById("globalConfirmModal")
  ) {
    // Fallback to native confirm if Bootstrap is not loaded
    var result = old_confirm(message);
    if (result && callback) {
      if (typeof callback === "string") {
        window.location.href = callback;
      } else if (typeof callback === "function") {
        callback();
      }

    } else if (
      !result &&
      cancelCallback &&
      typeof cancelCallback === "function"
    ) {
      cancelCallback();
    }
    return result;
  }

  // If no callback provided, try to auto-detect the event target
  // This supports the pattern: onclick="return confirm('are you sure')"
  if (callback === null && cancelCallback === null) {
    var targetElement = null;
    var targetEvent = null;

    // Try to find the event and target from the call stack
    try {
      // Check if there's a current event
      if (window.event) {
        targetEvent = window.event;
        targetElement = targetEvent.target || targetEvent.srcElement;
      }
    } catch (e) {
      // Ignore errors
    }

    // If we found a target element, set up auto-callback
    if (targetElement) {
      var originalHref = null;
      var isFormSubmit = false;
      var targetForm = null;

      // Check if it's a link
      if (targetElement.tagName === "A" && targetElement.href) {
        originalHref = targetElement.href;
        callback = function () {
          window.location.href = originalHref;
        };
      }
      // Check if it's a submit button or form
      else if (targetElement.tagName === "INPUT" && targetElement.type === "submit") {
        targetForm = targetElement.form;
        isFormSubmit = true;
      } else if (targetElement.tagName === "BUTTON" && targetElement.type === "submit") {
        targetForm = targetElement.form;
        isFormSubmit = true;
      } else if (targetElement.tagName === "FORM") {
        targetForm = targetElement;
        isFormSubmit = true;
      }

      // Set up form submit callback
      if (isFormSubmit && targetForm) {
        callback = function () {
          // Temporarily remove onsubmit to avoid infinite loop
          var originalOnsubmit = targetForm.onsubmit;
          targetForm.onsubmit = null;
          targetForm.submit();
          targetForm.onsubmit = originalOnsubmit;
        };
      }
    }
  }

  const modalElement = document.getElementById("globalConfirmModal");
  const modalTitle = document.getElementById("globalConfirmTitle");
  const modalMessage = document.getElementById("globalConfirmMessage");
  const modalConfirmBtn = document.getElementById("globalConfirmBtn");
  const modalCancelBtn = document.getElementById("globalCancelBtn");

  modalTitle.innerHTML =
    '<i class="bi bi-question-circle-fill me-2"></i>' + title;
  modalMessage.innerHTML = message;

  const modal = new bootstrap.Modal(modalElement);

  // Remove previous event listeners by cloning buttons
  const newConfirmBtn = modalConfirmBtn.cloneNode(true);
  modalConfirmBtn.parentNode.replaceChild(newConfirmBtn, modalConfirmBtn);

  const newCancelBtn = modalCancelBtn.cloneNode(true);
  modalCancelBtn.parentNode.replaceChild(newCancelBtn, modalCancelBtn);

  // Add confirm button event listener
  document.getElementById("globalConfirmBtn").onclick = function () {
    modal.hide();
    // Use setTimeout to ensure modal is hidden before executing callback
    setTimeout(function () {
      if (callback) {
        if (typeof callback === "string") {
          window.location.href = callback;
        } else if (typeof callback === "function") {
          callback();
        }
        else {
          return true;
        }
      }
      else {
        return true;
      }
    }, 100);
  };

  // Add cancel button event listener
  document.getElementById("globalCancelBtn").onclick = function () {
    modal.hide();
    if (cancelCallback && typeof cancelCallback === "function") {
      setTimeout(function () {
        cancelCallback();
      }, 100);
    }
    else {
      return false;
    }
  };

  modal.show();

  // Return false to prevent immediate execution
  return false;
}

/**
 * Show result modal (success or error)
 * Used for displaying operation results
 *
 * @param {boolean} success - True for success, false for error
 * @param {string} title - Modal title
 * @param {string} message - Message to display
 * @param {boolean} autoReload - Auto reload page after 2 seconds
 */
function showResultModal(success, title, message, autoReload) {
  if (typeof autoReload == "undefined") {
    autoReload = false;
  }

  // Check if Bootstrap modal is available
  if (
    typeof bootstrap === "undefined" ||
    !document.getElementById("globalResultModal")
  ) {
    // Fallback to alert
    alert(message);
    if (success && autoReload) {
      setTimeout(function () {
        location.reload();
      }, 2000);
    }
    return;
  }

  const modalElement = document.getElementById("globalResultModal");
  const modalHeader = document.getElementById("globalResultHeader");
  const modalIcon = document.getElementById("globalResultIcon");
  const modalTitle = document.getElementById("globalResultTitle");
  const modalMessage = document.getElementById("globalResultMessage");
  const modalReloadBtn = document.getElementById("globalResultReloadBtn");

  if (success) {
    modalHeader.style.background = "linear-gradient(135deg, #28a745, #20c997)";
    modalIcon.className = "bi bi-check-circle-fill me-2";
    modalTitle.textContent = title || "نجحت العملية";
  } else {
    modalHeader.style.background = "linear-gradient(135deg, #dc3545, #c82333)";
    modalIcon.className = "bi bi-x-circle-fill me-2";
    modalTitle.textContent = title || "فشلت العملية";
  }

  modalMessage.innerHTML = message;

  // Show/hide reload button
  if (autoReload && success) {
    modalReloadBtn.style.display = "inline-block";
  } else {
    modalReloadBtn.style.display = "none";
  }

  const modal = new bootstrap.Modal(modalElement);
  modal.show();

  // Auto reload after 2 seconds if success and autoReload is true
  if (success && autoReload) {
    setTimeout(function () {
      location.reload();
    }, 2000);
  }
}

$(function () {
  $(".button")
    .button()
    .click(function (event) {
      //event.preventDefault();
    });
});

$(document).ready(function () {
  // Check if jQuery validator is available before extending messages
  if (typeof jQuery.validator !== 'undefined') {
    jQuery.extend(jQuery.validator.messages, {
      required: "هذا الحقل مطلوب ",
      remote: "Please fix this field.",
      email: "الرجاء إدخال بريد صحيح ",
      url: "Please enter a valid URL.",
      date: "الرجاء ادخال يوم صحيح ",
      dateISO: "Please enter a valid date (ISO).",
      number: "الرجاء ادخال ارقام فقط .",
    digits: "الرجاء ادخال ارقام فقط .",
    creditcard: "Please enter a valid credit card number.",
    equalTo: "الرجاء ادخال نفس كلمة المرور .",
    accept: "Please enter a value with a valid extension.",
    maxlength: jQuery.validator.format(
      "Please enter no more than {0} characters."
    ),
    minlength: jQuery.validator.format("ادخل على الاقل  {0} حروف."),
    rangelength: jQuery.validator.format(
      "Please enter a value between {0} and {1} characters long."
    ),
    range: jQuery.validator.format("Please enter a value between {0} and {1}."),
    max: jQuery.validator.format(
      "Please enter a value less than or equal to {0}."
    ),
    min: jQuery.validator.format(
      "Please enter a value greater than or equal to {0}."
    ),
  });
  $("#mainform").validate({
    ignore: "*:not([name])",
    errorPlacement: function (error, element) {
      return true;
    },
  });
  }

  jQuery(function ($) {
    var currentMousePos = {
      x: -1,
      y: -1,
    };
    $(document).mousemove(function (event) {
      currentMousePos.x = event.pageX;
      currentMousePos.y = event.pageY;
    });
  });

  $("#menupalceholder").height($("#realmenu").height() - 15);
  $(".pagecontent").css("min-height", $(window).height() - 70);
  //$(".pagecontent").height($( window ).height() -560);
  $(window).resize(function () {
    $("#menupalceholder").height($("#realmenu").height() - 15);
  });
  setTimeout(function () {
    $("#menupalceholder").height($("#realmenu").height() - 15);
  }, 1000);
  setTimeout(function () {
    $("#menupalceholder").height($("#realmenu").height() - 15);
  }, 5000);
});

$(function () {
  $(".pdfbtn").click(function (event) {
    var pdfbtn = $(this);
    $(this).attr("src", "/aqary/admin/images/loadingsmall.gif");
    $.getJSON("/aqary/report.generator.hnt", {
      reportpath: $(this).attr("reportpath"),
      username: "<?= $_SESSION['usernamev'] ?>",
      password: "<?= $_SESSION['passwordv'] ?>",
    })
      .done(function (data) {
        //console.log( "second success" );
        window.location.href = data.filename;
      })
      .fail(function () {
        //console.log( "error " );
      })
      .always(function () {
        //console.log( "complete" );
        pdfbtn.attr("src", "/aqary/admin/images/pdf.png");
      });
  });
});

// ============================================
// URL and Navigation Functions
// ============================================

function gotourl(xurl, message) {
  if (typeof message == "undefined") {
    message = " هل أنت متأكد !!! ";
  }
  confirm(message, "سعودي عقار ", xurl);
}

function gotolink(xurl) {
  window.location.href = xurl;
  return false;
}

function myselect(inputname) {
  cc.xcd.value = 0;
  inputname.select();
  cc.xcd.value = 1;
}

function createXmlHttpObject() {
  var xmlHttp = null;
  try {
    // Firefox, Opera 8.0+, Safari
    xmlHttp = new XMLHttpRequest();
  } catch (e) {
    // Internet Explorer
    try {
      xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
      xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
  }
  return xmlHttp;
}

function ajaxpost(url, postdata, dividresponse) {
  if (typeof postdata == "undefined") {
    postdata = "";
  }
  if (typeof dividresponse == "undefined") {
    dividresponse = "responseTextx";
  }

  var xmlHttp = createXmlHttpObject();
  if (url.indexOf("?") == -1) {
    url += "?";
  }
  url = url + "&uniqsid=" + Math.random();

  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send(postdata);
  //xmlDoc=xmlHttp.responseText;
  //xmlHttp.open("POST", "demo_dom_http.asp", false);
  //xmlHttp.send(xmlDoc);

  function stateChanged() {
    if (dividresponse == "responseTextx" || dividresponse == "") {
      return true;
    }
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        if (document.getElementById(dividresponse).nodeName == "DIV") {
          document.getElementById(dividresponse).innerHTML =
            xmlHttp.responseText;
        } else {
          document.getElementById(dividresponse).value = xmlHttp.responseText;
        }
      } else {
        if (document.getElementById(dividresponse).nodeName == "DIV") {
          document.getElementById(dividresponse).innerHTML =
            "خطأ فى الحصول على البيانات ";
        } else {
          document.getElementById(dividresponse).value =
            "خطأ فى الحصول على البيانات ";
        }
      }
    }
  }
}

function ajaxfillcombox(combobj, sqlstr, defaultsel) {
  //if(typeof(postdata)=='undefined'){postdata="";}
  //if(typeof(dividresponse)=='undefined')
  {
    dividresponse = "responseTextx";
  }

  if (typeof defaultsel == "undefined") {
    defaultsel = 0;
  }

  var xmlHttp = createXmlHttpObject();
  url = "/aqary/ajaxfillcombox.do.hnt?";
  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send("sqlstr=" + sqlstr);
  //xmlDoc=xmlHttp.responseText;
  //xmlHttp.open("POST", "demo_dom_http.asp", false);
  //xmlHttp.send(xmlDoc);

  function stateChanged() {
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        alloptions = xmlHttp.responseText;
        //alert(alloptions);
        alloptions = alloptions.split("#@#");
        combobj.options.length = 1;
        for (var i = 0; i < alloptions.length; i = i + 2) {
          //alert(alloptions[i]);
          addOption(combobj, alloptions[i], alloptions[i + 1]);
          if (defaultsel == alloptions[i] && defaultsel > 0) {
            alert(alloptions[i]);
            combobj.options[combobj.options.length - 1].selected = true;
          }
        }
      } else {
        alert("خطأ فى الوضول للبيانات ");
      }
    }
  }
}

function ajaxfilldiv(
  sqlstr,
  objid,
  objtxt,
  sqlajcomboxid,
  sqlajcomboxtxt,
  ajpagenumv,
  eventCallerId
) {
  if (typeof sqlajcomboxid == "undefined") {
    sqlajcomboxid = objid;
  }
  if (typeof sqlajcomboxtxt == "undefined") {
    sqlajcomboxtxt = objtxt;
  }
  if (typeof ajpagenumv == "undefined") {
    ajpagenumv = 1;
  }
  if (typeof eventCallerId == "undefined") {
    ypos = currentMousePos.y - 20 + "px";
    xpos = currentMousePos.x - 300 + "px";
  } else {
    var fieldOffset = $("#" + eventCallerId).offset();
    var fieldHeight = $("#" + eventCallerId).outerHeight();
    var popupWidth = 500; // Estimated popup width
    var windowWidth = $(window).width();

    // Position below the field
    ypos = (fieldOffset.top + fieldHeight) + "px";

    // Position horizontally, ensuring it stays within viewport
    var leftPos = fieldOffset.left;

    // If popup would overflow right edge, align to right edge of field
    if (leftPos + popupWidth > windowWidth) {
      leftPos = Math.max(10, windowWidth - popupWidth - 10);
    }

    xpos = leftPos + "px";
  }

  ajcomboxobj = document.getElementById("ajcombox");
  ajcomboxcontentobj = document.getElementById("ajcomboxcontent");
  if (
    ajcomboxobj.style.display != "block" ||
    document.getElementById("ajcomboxreturnid").value != objid
  ) {
    ajcomboxobj.style.display = "block";
    ajcomboxobj.style.top = ypos; //getMouseY()-20;
    ajcomboxobj.style.left = xpos; //getMouseX()-100;
    document.getElementById("ajcomboxsearchid").focus();
  }
  document.getElementById("ajcomboxreturnid").value = objid;
  document.getElementById("ajcomboxreturntxt").value = objtxt;
  document.getElementById("sqlajcomboxsearchid").value = sqlajcomboxid;
  document.getElementById("sqlajcomboxsearchtxt").value = sqlajcomboxtxt;
  document.getElementById("ajcomboxsql").value = sqlstr;

  var xmlHttp = createXmlHttpObject();
  url = "/aqary/ajaxfilldiv.do.hnt?";
  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send("sqlstr=" + sqlstr + "&ajpagenumv=" + ajpagenumv);

  function stateChanged() {
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        ajcomboxcontentobj.innerHTML = xmlHttp.responseText;
        // alert(ajcomboxcontentobj);
      } else {
        alert("خطأ فى الوضول للبيانات ");
      }
    }
  }
}

function ajaxfillgrid(sqlstr, containerid, incremental, ajdpagenumv, mylink) {
  if (typeof incremental == "undefined") {
    incremental = false;
  }
  if (typeof ajdpagenumv == "undefined") {
    ajdpagenumv = 1;
  }
  if (typeof mylink == "undefined") {
    mylink = "";
  }
  if (typeof containerid == "undefined") {
    containerid = "containerid";
  }

  var xmlHttp = createXmlHttpObject();
  url = "/aqary/ajaxfillgrid.do.hnt?";
  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send(
    "sqlstr=" + sqlstr + "&ajpagenumv=" + ajdpagenumv + "&mylink=" + mylink
  );

  function stateChanged() {
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        if (incremental) {
          document.getElementById(containerid).innerHTML = xmlHttp.responseText;
        } else {
          document.getElementById(containerid).innerHTML =
            document.getElementById(containerid).innerHTML +
            xmlHttp.responseText;
        }
      } else {
        alert("خطأ فى الوضول للبيانات ");
      }
    }
  }
}

function ajaxmysql(sqlstr, returntxtid, mylink) {
  if (typeof mylink == "undefined") {
    mylink = "";
  }
  if (typeof returntxtid == "undefined" || returntxtid == "") {
    var returntxt = "";
    returntxtid = "";
  } else {
    var returntxt = document.getElementById(returntxtid);
  }
  var xmlHttp = createXmlHttpObject();
  url = "/aqary/ajaxmysql.do.hnt?";
  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send("sqlstr=" + sqlstr + "&mylink=" + mylink);

  function stateChanged() {
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        returntxt.value = xmlHttp.responseText;
      } else {
        alert("خطأ فى الوضول للبيانات error:");
      }
    }
  }
}

function ajaxmysqlrow(sqlstr, returntxtid, mylink) {
  if (typeof mylink == "undefined") {
    mylink = "";
  }
  if (typeof returntxtid == "undefined" || returntxtid == "") {
    var returntxt = "";
    returntxtid = "";
  } else {
    var returntxt = document.getElementById(returntxtid);
  }
  var xmlHttp = createXmlHttpObject();
  url = "/aqary/ajaxmysqlrow.do.hnt?";
  xmlHttp.onreadystatechange = stateChanged;
  xmlHttp.open("POST", url, true);
  xmlHttp.setRequestHeader(
    "Content-type",
    "application/x-www-form-urlencoded;charset=utf-8"
  );
  xmlHttp.send("sqlstr=" + sqlstr + "&mylink=" + mylink);

  //xmlDoc=xmlHttp.responseText;
  //xmlHttp.open("POST", "demo_dom_http.asp", false);
  //xmlHttp.send(xmlDoc);

  function stateChanged() {
    if (xmlHttp.readyState == 4) {
      if (xmlHttp.status == 200) {
        returntxt.value = xmlHttp.responseText;
      } else {
        alert("خطأ فى الوضول للبيانات error:");
      }
    }
  }
}

function addOption(selectbox, text, value) {
  var optn = document.createElement("OPTION");
  optn.text = text;
  optn.value = value;
  selectbox.options.add(optn);
}

function delRow() {
  var current = window.event.srcElement;
  //here we will delete the line
  while ((current = current.parentElement) && current.tagName != "TR");
  current.parentElement.removeChild(current);
}

function deleteRow(xtr) {
  xtr.parentNode.deleteRow(xtr.rowIndex);
}

function deleteRowtd(xtd) {
  var current = xtd;
  //here we will delete the line
  while ((current = current.parentElement) && current.tagName != "TR");
  current.parentElement.removeChild(current);
}

function convertEnterToTab(event) {
  // Get the target element
  const target = event.target || event.srcElement;

  // Allow Enter key in textarea and other elements where it should work normally
  if (target.type === "textarea" || target.tagName === "TEXTAREA" ||
      target.tagName === "DIV" || target.tagName === "TD" || target.tagName === "TABLE") {
    return;
  }

  // Prevent Enter from submitting forms - move to next field instead
  if (event.keyCode === 13 || event.key === "Enter") {
    event.preventDefault();
    event.stopPropagation();

    // Programmatically move focus to next element
    const form = target.closest("form");
    if (form) {
      const elements = Array.from(form.elements);
      const currentIndex = elements.indexOf(target);
      if (currentIndex !== -1 && currentIndex < elements.length - 1) {
        elements[currentIndex + 1].focus();
      }
    }
  }
}

// Assign function as event handler (not calling it)
document.addEventListener("keydown", convertEnterToTab);

function jqmsbox(htmlcontent) {
  $.msg({
    autoUnblock: false,
    clickUnblock: true,
    content: htmlcontent,
    afterBlock: function () {
      // store 'this' for other scope to use
      var self = this;

      $(".no").bind("click", function () {
        self.unblock();
      });
    },
  });
}

$(function () {
  $(".rtl-date").each(function (index) {
    $(this).text($(this).text().split("/").reverse().join("-"));
  });
});
