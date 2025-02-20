document
  .querySelector('form[action="updateCustomization"]')
  ?.addEventListener("submit", function (e) {
    // Força o reload do CSS
    const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
    stylesheets.forEach((stylesheet) => {
      if (stylesheet.href.includes("dynamic-styles.php")) {
        const timestamp = new Date().getTime();
        stylesheet.href = stylesheet.href.split("?")[0] + "?v=" + timestamp;
      }
    });
  });
