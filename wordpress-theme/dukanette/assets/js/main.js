(function () {
  "use strict";

  var toggle = document.querySelector(".mobile-nav-toggle");
  var mobileNav = document.getElementById("mobile-nav");
  if (toggle && mobileNav) {
    toggle.addEventListener("click", function () {
      var expanded = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", String(!expanded));
      mobileNav.hidden = expanded;
    });
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest(".favorite-btn[data-post-id]");
    if (!button || !window.dukanetteData) return;

    event.preventDefault();
    button.disabled = true;

    var body = new URLSearchParams({
      action: "dukanette_toggle_favorite",
      nonce: window.dukanetteData.nonce,
      post_id: button.dataset.postId,
    });

    fetch(window.dukanetteData.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          var active = json.data.favorited;
          button.classList.toggle("is-active", active);
          button.setAttribute("aria-pressed", String(active));
          button.textContent = active ? "❤" : "♡";
        }
      })
      .finally(function () {
        button.disabled = false;
      });
  });

  var newsletterForm = document.querySelector(".newsletter-form");
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", function (event) {
      event.preventDefault();
      var emailInput = newsletterForm.querySelector('input[type="email"]');
      var status = newsletterForm.querySelector(".newsletter-status");
      var button = newsletterForm.querySelector('button[type="submit"]');

      button.disabled = true;
      status.textContent = "";

      var body = new URLSearchParams({
        action: "dukanette_newsletter_subscribe",
        nonce: window.dukanetteData.nonce,
        email: emailInput.value,
      });

      fetch(window.dukanetteData.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            newsletterForm.hidden = true;
            status.textContent = "Merci ! Vérifie ta boîte mail pour confirmer ton inscription.";
            status.hidden = false;
          } else {
            status.textContent = (json.data && json.data.message) || "Une erreur est survenue.";
            status.hidden = false;
          }
        })
        .catch(function () {
          status.textContent = "Une erreur est survenue.";
          status.hidden = false;
        })
        .finally(function () {
          button.disabled = false;
        });
    });
  }
})();
