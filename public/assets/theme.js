// Applies the saved theme before the first paint so the page never flashes the wrong colours.
(function () {
  try {
    var theme = localStorage.getItem('sc-theme');
    if (theme === 'light' || theme === 'dark') {
      document.documentElement.setAttribute('data-theme', theme);
    }
  } catch (e) {
    // Storage can be blocked; the system theme still applies.
  }
})();
