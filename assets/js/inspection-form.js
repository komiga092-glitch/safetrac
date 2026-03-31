document.addEventListener("DOMContentLoaded", function () {
  function toggleFailFields(itemId) {
    const failBlock = document.querySelector(".fail-fields-" + itemId);
    const failInputs = document.querySelectorAll(".fail-required-" + itemId);
    const selected = document.querySelector(
      '.response-select[data-item="' + itemId + '"]:checked',
    );
    const value = selected ? selected.value : "";

    if (!failBlock) return;

    if (value === "No" || value === "Fail") {
      failBlock.style.display = "block";
      failInputs.forEach(function (input) {
        input.setAttribute("required", "required");
      });
    } else {
      failBlock.style.display = "none";
      failInputs.forEach(function (input) {
        input.removeAttribute("required");
        if (input.tagName === "SELECT") {
          input.selectedIndex = 0;
        } else if (input.type !== "file") {
          input.value = "";
        }
      });
    }
  }

  const radioInputs = document.querySelectorAll(".response-select");
  const handledItems = new Set();

  radioInputs.forEach(function (radio) {
    const itemId = radio.getAttribute("data-item");

    if (!handledItems.has(itemId)) {
      toggleFailFields(itemId);
      handledItems.add(itemId);
    }

    radio.addEventListener("change", function () {
      toggleFailFields(itemId);
    });
  });
});
