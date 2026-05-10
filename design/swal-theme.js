// swal-theme.js — themed SweetAlert2 mixin matching JuMA design system

(function() {
  const css = `
    .swal2-container.juma-container { z-index: 2147483645; }
    .swal2-popup.juma-popup {
      font-family: 'Manrope', system-ui, sans-serif;
      border-radius: 22px;
      padding: 24px 24px 20px;
      background: var(--surface);
      color: var(--text);
      border: 1px solid var(--border);
      box-shadow: 0 24px 60px rgba(0,0,0,0.25);
      width: 360px;
    }
    .swal2-popup.juma-popup .swal2-title {
      font-family: 'Manrope', system-ui, sans-serif;
      font-size: 20px;
      font-weight: 700;
      letter-spacing: -0.015em;
      color: var(--text);
      padding: 0;
      margin-bottom: 6px;
    }
    .swal2-popup.juma-popup .swal2-html-container {
      font-size: 14px;
      line-height: 1.45;
      color: var(--text-muted);
      margin: 6px 0 18px;
      padding: 0;
    }
    .swal2-popup.juma-popup .juma-summary {
      display: flex; align-items: flex-end; gap: 12px;
      padding: 14px 16px;
      background: var(--surface-alt);
      border-radius: 14px;
      margin: 14px 0 4px;
    }
    .swal2-popup.juma-popup .juma-summary .num {
      font-family: 'JetBrains Mono', monospace;
      font-size: 38px;
      font-weight: 600;
      letter-spacing: -0.04em;
      line-height: 0.95;
      color: var(--text);
    }
    .swal2-popup.juma-popup .juma-summary .lbl {
      font-size: 11px;
      color: var(--text-subtle);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 700;
      padding-bottom: 4px;
    }
    .swal2-popup.juma-popup .juma-summary .grp {
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      margin-top: 2px;
    }
    .swal2-popup.juma-popup .swal2-actions {
      display: flex !important;
      gap: 8px !important;
      width: 100% !important;
      margin: 14px 0 0 !important;
      flex-direction: column-reverse !important;
      align-items: stretch !important;
    }
    .swal2-popup.juma-popup .swal2-styled {
      font-family: 'Manrope', system-ui, sans-serif !important;
      font-weight: 600 !important;
      font-size: 14.5px !important;
      height: 48px !important;
      width: 100% !important;
      border-radius: 12px !important;
      margin: 0 !important;
      padding: 0 18px !important;
      box-sizing: border-box !important;
      box-shadow: none !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      transition: background 120ms ease !important;
    }
    .swal2-popup.juma-popup .swal2-icon {
      margin: 4px auto 12px !important;
      width: 56px !important;
      height: 56px !important;
    }
    .swal2-popup.juma-popup .swal2-icon .swal2-icon-content {
      font-size: 32px !important;
    }
    .swal2-popup.juma-popup .swal2-confirm.juma-btn {
      background: var(--red) !important;
      color: #fff !important;
    }
    .swal2-popup.juma-popup .swal2-confirm.juma-btn:hover {
      background: var(--red-hover) !important;
    }
    .swal2-popup.juma-popup .swal2-cancel.juma-btn-cancel {
      background: var(--surface-alt) !important;
      color: var(--text) !important;
      border: 1px solid var(--border-strong) !important;
    }
    .swal2-popup.juma-popup .swal2-deny.juma-btn-deny {
      background: transparent !important;
      color: var(--text-muted) !important;
      height: 36px !important;
    }

    /* Toast variant */
    .swal2-popup.juma-toast {
      font-family: 'Manrope', system-ui, sans-serif !important;
      border-radius: 14px !important;
      padding: 12px 16px !important;
      background: var(--text) !important;
      color: var(--bg) !important;
      box-shadow: 0 12px 40px rgba(0,0,0,0.3) !important;
    }
    .swal2-popup.juma-toast .swal2-title {
      font-family: 'Manrope', system-ui, sans-serif !important;
      font-size: 13.5px !important;
      font-weight: 600 !important;
      color: var(--bg) !important;
    }
    .swal2-popup.juma-toast .swal2-html-container {
      font-size: 12px !important;
      color: rgba(255,255,255,0.65) !important;
    }

    .swal2-icon.swal2-success [class^='swal2-success-line'] {
      background-color: var(--ok) !important;
    }
    .swal2-icon.swal2-success .swal2-success-ring {
      border-color: var(--ok) !important;
    }
    .swal2-icon.swal2-warning {
      border-color: var(--warn) !important;
      color: var(--warn) !important;
    }
    .swal2-icon.swal2-question {
      border-color: var(--red) !important;
      color: var(--red) !important;
    }
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  const baseConfig = {
    customClass: {
      container: 'juma-container',
      popup: 'juma-popup',
      confirmButton: 'juma-btn',
      cancelButton: 'juma-btn-cancel',
      denyButton: 'juma-btn-deny',
    },
    buttonsStyling: false,
    reverseButtons: false,
    showClass: { popup: 'swal2-show' },
    hideClass: { popup: 'swal2-hide' },
  };

  window.JuMAlert = window.Swal.mixin(baseConfig);

  window.JuMAToast = window.Swal.mixin({
    toast: true,
    position: 'top',
    showConfirmButton: false,
    timer: 2400,
    timerProgressBar: true,
    customClass: {
      popup: 'juma-toast',
      container: 'juma-container',
    },
  });
})();
