/**
 * app.diagrams.net: hash #R… jak „Export → URL” (encodeURIComponent → deflateRaw → base64).
 */
(function (global) {
  const EASYC4_CLIBS =
    'Uhttps%3A%2F%2Fraw.githubusercontent.com%2Fmaciek365%2Fc4-diagrams.net%2Fmaster%2FEasyC4';
  const DIAGRAMS_EDITOR = `https://app.diagrams.net/?splash=0&clibs=${EASYC4_CLIBS}`;
  const MAX_URL_LEN = 62000;

  function loadScriptOnce(src) {
    return new Promise((resolve, reject) => {
      const existing = document.querySelector(`script[data-src="${src}"]`);
      if (existing) {
        existing.addEventListener('load', () => resolve(), { once: true });
        existing.addEventListener('error', () => reject(new Error('Failed to load ' + src)), { once: true });
        // If it already loaded earlier, resolve immediately.
        if (existing.getAttribute('data-loaded') === '1') resolve();
        return;
      }
      const s = document.createElement('script');
      s.async = true;
      s.src = src;
      s.setAttribute('data-src', src);
      s.addEventListener('load', () => {
        s.setAttribute('data-loaded', '1');
        resolve();
      }, { once: true });
      s.addEventListener('error', () => reject(new Error('Failed to load ' + src)), { once: true });
      document.head.appendChild(s);
    });
  }

  async function ensurePakoAvailable() {
    if (global.pako && global.pako.deflateRaw) return;
    await loadScriptOnce('assets/vendor/pako.min.js');
    if (!global.pako || !global.pako.deflateRaw) {
      throw new Error('Compression is not available (pako did not load). Please refresh the page.');
    }
  }

  function u8ToBinaryString(u8) {
    let s = '';
    const chunk = 0x8000;
    for (let i = 0; i < u8.length; i += chunk) {
      s += String.fromCharCode.apply(null, u8.subarray(i, Math.min(i + chunk, u8.length)));
    }
    return s;
  }

  async function compressToRFragmentAsync(xml) {
    const enc = encodeURIComponent(xml);
    const input = new TextEncoder().encode(enc);
    let deflated;
    if (typeof CompressionStream !== 'undefined') {
      const cs = new CompressionStream('deflate-raw');
      const buf = await new Response(new Blob([input]).stream().pipeThrough(cs)).arrayBuffer();
      deflated = new Uint8Array(buf);
    } else {
      await ensurePakoAvailable();
      deflated = global.pako.deflateRaw(enc);
    }
    const b64 = btoa(u8ToBinaryString(deflated));
    return 'R' + encodeURIComponent(b64);
  }

  global.OpenDiagramsNet = {
    DIAGRAMS_EDITOR,
    MAX_URL_LEN,
    compressToRFragmentAsync,
    async openWithXml(xml) {
      const hash = await compressToRFragmentAsync(xml);
      const url = DIAGRAMS_EDITOR + '#' + hash;
      if (url.length > MAX_URL_LEN) {
        const err = new Error(
          'This diagram generates a very long URL (browser limit). ' +
            'Download the .drawio file and in diagrams.net use: File → Open from → Device.'
        );
        err.code = 'URL_TOO_LONG';
        throw err;
      }
      global.open(url, '_blank', 'noopener,noreferrer');
    },
  };
})(typeof window !== 'undefined' ? window : globalThis);

