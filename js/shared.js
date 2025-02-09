LoadNote();

async function LoadNote() {
  const params = new URLSearchParams(window.location.search);
  const SharedID = params.get("id");

  const note = await callApi({
    action: "ShareNoteGet",
    params: {
      id: SharedID,
    },
  });

  if (note && note?.status === "success") {
    let noteContent = note?.data?.note_content;
    let HtmlData = `<div class="paper" contenteditable="false" id="paper"">${noteContent}`;

    // replacing all \n with new div
    HtmlData = HtmlData.replaceAll(/\n/g, "<br>");

    const images = note?.data?.note_images ?? [];
    images.forEach((image) => {
      HtmlData = addImage(HtmlData, image);
    });

    const checklists_data = note?.data?.checklist ?? [];
    checklists_data.forEach((list) => {
      HtmlData = addChecklist(HtmlData, list);
    });

    HtmlData += "</div>";

    document.body.innerHTML = `
      <div class="top">
        ${
          note?.data?.logged_in
            ? `<i class="fas fa-home back" onclick="home()"></i>`
            : ""
        }
        <div contenteditable="false" id="editable">${
          note?.data?.note_name ? note?.data?.note_name : "Untitled Note"
        }</div>
        <div class="menu-container">
          <i class="fa-solid fa-bars menu-icon" onclick="toggleMenu()"></i>
          <div class="menu-items" id="menu">
            <button class="menu-item" onclick="exportToPDF()">
              <i class="fas fa-download"></i>Export to PDF
            </button>
          </div>
        </div>
      </div>

      <div id="notes">
        ${HtmlData}
      </div>

      <div class="limit">${noteContent.length} / 60000</div>
      `;
  }
}

// Add an image
function addImage(HtmlData, image) {
  return HtmlData.replace(
    `{[image:${image?.id}]}`,
    `</div>
      <div class="image-container">
        <img src="${image.image}" id="${image.id}">
      </div>
      <div class="paper" contenteditable="false" id="paper">`
  );
}

// Add a checklist (with original ID from DB)
function addChecklist(HtmlData, list) {
  const checklistId = list.id;

  if (list && list.lists && list.lists.length > 0) {
    let checklistItems = list.lists
      .map((item) => {
        return `
          <div class="checklist-item">
            <input class="${
              item.checked ? "checked" : ""
            }" type="text" value="${
          item.content.trim() || ""
        }" placeholder="" readonly>
        </div>`;
      })
      .join("");

    HtmlData = HtmlData.replace(
      `{[check:${list.id}]}`,
      `</div>
        <div class="checklist" id="${checklistId}">
          ${checklistItems}
        </div>
        <div class="paper" contenteditable="false" id="paper">`
    );
  }

  return HtmlData;
}

// Tongle Menu
function toggleMenu() {
  const menu = document.getElementById("menu");
  menu.classList.toggle("show");
}

document.addEventListener("click", function (event) {
  const menu = document.getElementById("menu");
  const menuIcon = document.querySelector(".menu-icon");
  if (!menu.contains(event.target) && !menuIcon.contains(event.target)) {
    menu.classList.remove("show");
  }
});

async function exportToPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  const noteTitle =
    document.getElementById("editable").innerText.trim() || "Untitled Note";
  const pageWidth = doc.internal.pageSize.getWidth();
  const textWidth = doc.getTextWidth(noteTitle);
  const centerX = (pageWidth - textWidth) / 2;
  doc.setFontSize(16);
  doc.text(noteTitle, centerX, 10);
  let y = 20;
  const notesContainer = document.getElementById("notes");
  const elements = Array.from(notesContainer.children);

  for (const element of elements) {
    if (element.classList.contains("paper")) {
      let text = element.innerText;
      let lines = doc.splitTextToSize(text, 180);
      doc.setFontSize(12);
      doc.text(lines, 10, y);
      y += lines.length * 5;
    } else if (element.classList.contains("image-container")) {
      const img = element.querySelector("img");
      if (img) {
        y += 5;
        const imgData = img.src;
        const imgWidth = 190;
        const imgHeight = 50;
        doc.addImage(imgData, "JPEG", 10, y, imgWidth, imgHeight);
        y += imgHeight + 10;
      }
    } else if (element.classList.contains("checklist")) {
      const checkItems = element.querySelectorAll(".checklist-item");
      doc.setFontSize(12);
      y += 10;
      checkItems.forEach((item) => {
        const input = item.querySelector("input");
        const checked = input.classList.contains("checked")
          ? "[ x ] "
          : "[   ] ";
        const textLines = doc.splitTextToSize(`${checked}${input.value}`, 180);
        textLines.forEach((line) => {
          doc.text(line, 10, y);
          y += 6;
        });
        y += 4;
      });
    }

    if (y > 270) {
      doc.addPage();
      y = 10;
    }
  }

  doc.save(`${noteTitle || "Note"}.pdf`);
}

async function home() {
  window.location.href = "./";
}

// API helper function
async function callApi(query) {
  const data = await fetch("./api.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(query),
  }).then((response) => response.json());

  if (data?.redirect) {
    window.location.href = `${data?.redirect}`;
  } else if (data?.status === "error") {
    new swal("Error", `${data?.message}`, "error");
  } else {
    return data;
  }
}
