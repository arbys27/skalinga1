/**
 * ════════════════════════════════════════════════════════════════
 * Professional Loading Manager - SKalinga Youth Portal
 * ════════════════════════════════════════════════════════════════
 * 
 * A reusable global loading overlay manager with:
 * - Auto-hide loader when page/requests finish
 * - Fetch API interception
 * - Form submission handling
 * - Customizable messages and animations
 * 
 * Usage:
 *   window.Loader.show('Loading data...');
 *   window.Loader.hide();
 *   window.Loader.setAnimation('spinner'); // or 'shield', 'dots'
 */

window.Loader = (function() {
  'use strict';

  // ─────────────────────────────────────────────────────────────
  // Configuration
  // ─────────────────────────────────────────────────────────────
  const config = {
    animation: 'spinner', // 'spinner', 'shield', 'dots', 'svg'
    fadeOutDelay: 300,     // ms delay before fading out
    minShowTime: 300,      // minimum time to show loader (prevents flickering)
    autoShowDelay: 500,    // auto-show if request takes longer than this
    showProgressBar: false // show progress bar for longer operations
  };

  let loaderElement = null;
  let showStartTime = 0;
  let isVisible = false;
  let autoHideTimeout = null;
  let minShowTimeout = null;
  let requestCount = 0; // Track active requests

  // ─────────────────────────────────────────────────────────────
  // Initialize Loader HTML
  // ─────────────────────────────────────────────────────────────
  function initLoader() {
    // Check if already initialized
    if (document.getElementById('global-loader')) {
      loaderElement = document.getElementById('global-loader');
      return;
    }

    // Create loader HTML
    const loader = document.createElement('div');
    loader.id = 'global-loader';
    loader.className = 'loader-overlay';
    loader.innerHTML = `
      <div class="loader-content">
        <!-- Circular Spinner (Default) -->
        <div class="loader-spinner" id="spinner-animation">
          <div class="spinner-ring"></div>
        </div>

        <!-- Shield Icon -->
        <div class="loader-shield" id="shield-animation" style="display: none;">
          <div class="shield-icon">S</div>
        </div>

        <!-- Dot Loader -->
        <div class="loader-dots" id="dots-animation" style="display: none;">
          <div class="dot"></div>
          <div class="dot"></div>
          <div class="dot"></div>
        </div>

        <!-- SVG Loader (Optional) -->
        <div class="loader-svg" id="svg-animation" style="display: none;">
          <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="loaderGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#5DADE2;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#228B57;stop-opacity:1" />
              </linearGradient>
            </defs>
            <circle cx="50" cy="50" r="40" fill="none" stroke="url(#loaderGradient)" stroke-width="3"/>
            <circle cx="50" cy="50" r="35" fill="none" stroke="rgba(162, 210, 223, 0.2)" stroke-width="2"/>
          </svg>
        </div>

        <!-- Progress Bar (Optional) -->
        <div class="loader-progress-bar" id="loader-progress">
          <div class="progress-fill"></div>
        </div>
      </div>
    `;

    document.body.appendChild(loader);
    loaderElement = loader;

    // Link stylesheet if not already linked
    if (!document.getElementById('loader-css-link')) {
      const link = document.createElement('link');
      link.id = 'loader-css-link';
      link.rel = 'stylesheet';
      link.href = 'assets/css/loader.css?v=3';
      document.head.appendChild(link);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // Show Loader
  // ─────────────────────────────────────────────────────────────
  function show(_message, options = {}) {
    initLoader();

    const mergedOptions = { ...config, ...options };

    // Switch animation type
    setAnimation(mergedOptions.animation);

    // Show progress bar if configured
    const progressBar = document.getElementById('loader-progress');
    if (mergedOptions.showProgressBar) {
      progressBar.classList.add('show');
    }

    // Show overlay
    loaderElement.classList.add('show');
    loaderElement.classList.remove('hide');
    isVisible = true;
    showStartTime = Date.now();

    // Log for debugging
    console.log('[Loader] Shown');
  }

  // ─────────────────────────────────────────────────────────────
  // Hide Loader
  // ─────────────────────────────────────────────────────────────
  function hide(delay = config.fadeOutDelay) {
    if (!isVisible || !loaderElement) return;

    // Calculate time shown
    const timeShown = Date.now() - showStartTime;
    const remainingMinTime = Math.max(0, config.minShowTime - timeShown);

    // Ensure minimum show time to prevent flickering
    const totalDelay = Math.max(delay, remainingMinTime);

    // Clear any existing timeout
    if (autoHideTimeout) {
      clearTimeout(autoHideTimeout);
    }

    autoHideTimeout = setTimeout(() => {
      if (!loaderElement) return;

      loaderElement.classList.remove('show');
      loaderElement.classList.add('hide');
      isVisible = false;

      // Reset states
      const progressBar = document.getElementById('loader-progress');
      if (progressBar) {
        progressBar.classList.remove('show');
      }

      console.log('[Loader] Hidden');
    }, totalDelay);
  }

  // ─────────────────────────────────────────────────────────────
  // Set Animation Type
  // ─────────────────────────────────────────────────────────────
  function setAnimation(type = 'spinner') {
    initLoader();

    // Hide all animations
    document.getElementById('spinner-animation').style.display = 'none';
    document.getElementById('shield-animation').style.display = 'none';
    document.getElementById('dots-animation').style.display = 'none';
    document.getElementById('svg-animation').style.display = 'none';

    // Show selected animation
    switch (type) {
      case 'shield':
        document.getElementById('shield-animation').style.display = 'flex';
        break;
      case 'dots':
        document.getElementById('dots-animation').style.display = 'flex';
        break;
      case 'svg':
        document.getElementById('svg-animation').style.display = 'flex';
        break;
      case 'spinner':
      default:
        document.getElementById('spinner-animation').style.display = 'flex';
        config.animation = 'spinner';
    }

    console.log('[Loader] Animation set to:', type);
  }

  // ─────────────────────────────────────────────────────────────
  // Intercept Fetch Calls
  // ─────────────────────────────────────────────────────────────
  function interceptFetch() {
    const originalFetch = window.fetch;

    window.fetch = function(...args) {
      requestCount++;
      console.log('[Loader] Fetch started. Active requests:', requestCount);

      // Auto-show loader if request takes too long
      const autoShowTimer = setTimeout(() => {
        if (requestCount > 0 && !isVisible) {
          show();
        }
      }, config.autoShowDelay);

      return originalFetch.apply(this, args)
        .then(response => {
          requestCount--;
          clearTimeout(autoShowTimer);
          console.log('[Loader] Fetch completed. Active requests:', requestCount);

          // Hide loader if all requests are done
          if (requestCount === 0 && isVisible) {
            hide();
          }

          return response;
        })
        .catch(error => {
          requestCount--;
          clearTimeout(autoShowTimer);
          console.error('[Loader] Fetch error:', error);

          // Hide loader on error
          if (requestCount === 0 && isVisible) {
            hide();
          }

          throw error;
        });
    };

    console.log('[Loader] Fetch interceptor installed');
  }

  // ─────────────────────────────────────────────────────────────
  // Auto-hide on Page Load Complete
  // ─────────────────────────────────────────────────────────────
  function setupPageLoadHandler() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        console.log('[Loader] Page DOM loaded');
        // Give a moment for any initial fetch calls to start
        setTimeout(() => {
          if (requestCount === 0) {
            hide();
          }
        }, 100);
      });

      window.addEventListener('load', () => {
        console.log('[Loader] Page fully loaded');
        if (requestCount === 0) {
          hide();
        }
      });
    } else {
      // Page already loaded
      console.log('[Loader] Page already loaded');
    }
  }

  // ─────────────────────────────────────────────────────────────
  // Auto-handle Form Submissions
  // ─────────────────────────────────────────────────────────────
  function setupFormHandler() {
    document.addEventListener('submit', function(e) {
      const form = e.target;
      
      // Check if form has data-no-loader attribute
      if (form.hasAttribute('data-no-loader')) {
        return;
      }

      show();
      console.log('[Loader] Form submitted');
    }, true);
  }

  // ─────────────────────────────────────────────────────────────
  // Public API
  // ─────────────────────────────────────────────────────────────
  return {
    show: show,
    hide: hide,
    setAnimation: setAnimation,
    setConfig: function(options) {
      Object.assign(config, options);
      console.log('[Loader] Config updated:', config);
    },
    init: function() {
      initLoader();
      interceptFetch();
      setupPageLoadHandler();
      setupFormHandler();
      console.log('[Loader] System initialized');
    },
    // Get current state
    isLoading: function() {
      return isVisible;
    },
    // Debugging
    getConfig: function() {
      return { ...config };
    },
    getActiveRequests: function() {
      return requestCount;
    }
  };
})();

// ─────────────────────────────────────────────────────────────
// Auto-initialize on script load
// ─────────────────────────────────────────────────────────────
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() {
    window.Loader.init();
  });
} else {
  window.Loader.init();
}
