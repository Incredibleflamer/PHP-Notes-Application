let NotesData = {};

LoadNote();

async function LoadNote() {
  const params = new URLSearchParams(window.location.search);
  const noteId = params.get("id");

  const note = await callApi({
    action: "getNote",
    params: {
      note_id: noteId,
    },
  });

  if (note && note?.status === "success") {
    NotesData = {
      id: note?.data?.note_id,
      content: note?.data?.note_content,
      name: note?.data?.note_name,
      sharing_info: note?.data?.sharing_info,
    };
    let noteContent = note?.data?.note_content;
    let HtmlData = `<div class="paper" contenteditable="true" id="paper" oninput="updateWordCount()">${noteContent}`;

    // replacing all \n with new div
    HtmlData = HtmlData.replaceAll("\n", "<br>");

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
        <i class="fas fa-arrow-left back" onclick="goback()"></i>
        <div contenteditable="true" id="editable" oninput="HandleName(this)">${
          note?.data?.note_name ? note?.data?.note_name : "Untitled Note"
        }</div>
        <div class="menu-container">
          <i class="fa-solid fa-bars menu-icon" onclick="toggleMenu()"></i>
          <div class="menu-items" id="menu">
            <button class="menu-item" onclick="triggerImageUpload()">
              <i class="fas fa-image"></i>Add Image
            </button>
            <button class="menu-item" onclick="createChecklist()">
              <i class="fas fa-list"></i>Add CheckList
            </button>
            <button class="menu-item" onclick="exportToPDF()">
              <i class="fas fa-download"></i>Export to PDF
            </button>
            <button class="menu-item" onclick="loadSharingScreen()">
              <i class="fas fa-share"></i>Share
            </button>
          </div>
        </div>
      </div>

      <div id="notes">
        ${HtmlData}
      </div>

      <input type="file" id="imageUploader" accept="image/*" style="display: none;" onchange="handleImageUpload(event)" />

      <div class="limit">${noteContent.length} / 60000</div>
      <div id="SavedOrNot">Not Saved</div>
      `;

    document.getElementById("editable").addEventListener("input", function () {
      const maxLength = 100;
      if (this.innerText.length > maxLength) {
        this.innerText = this.innerText.substring(0, maxLength);
      }
    });

    const paper = document.querySelector(".paper");
    paper.addEventListener("input", updateWordCount);
    updateWordCount();

    document.addEventListener("keydown", async function (event) {
      if (event.ctrlKey && event.key.toLowerCase() === "s") {
        event.preventDefault();
        let results = await SaveData(true);
        if (results && results?.status === "success") {
          // Alert saved
        } else {
          // TODO: Alert error
        }
      }
    });
  } else {
    window.location = "./404.html";
  }
}

// Add an image
function addImage(HtmlData, image) {
  return HtmlData.replace(
    `{[image:${image?.id}]}`,
    `</div>
      <div class="image-container">
        <img src="${image.image}" id="${image.id}">
        <div class="delete-icon" onclick="deleteImage('${image.id}')">×</div>
      </div>
      <div class="paper" contenteditable="true" id="paper" oninput="updateWordCount()">`
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
            <div class="checkbox" onclick="toggleCheckbox(this)">✔</div>
            <input class="${
              item.checked ? "checked" : ""
            }" type="text" value="${
          item.content.trim() || ""
        }" placeholder="Type your task..." onkeydown="checkEnter(event, this)">
            <button class="move-btn" onclick="moveUp(this)">⬆</button>
            <button class="move-btn" onclick="moveDown(this)">⬇</button>
            <button class="remove-btn" onclick="removeItem(this, ${checklistId})">X</button>
          </div>`;
      })
      .join("");

    HtmlData = HtmlData.replace(
      `{[check:${list.id}]}`,
      `</div>
        <div class="checklist" id="${checklistId}">
          ${checklistItems}
        </div>
        <div class="paper" contenteditable="true" id="paper" oninput="updateWordCount()">`
    );
  }

  return HtmlData;
}

// Handle image delete
function deleteImage(imageId) {
  const imageElement = document.getElementById(imageId);
  if (imageElement) {
    const imageContainer = imageElement.closest(".image-container");
    if (imageContainer) {
      const previousPaper = imageContainer.previousElementSibling;
      const nextPaper = imageContainer.nextElementSibling;
      imageContainer.remove();

      if (previousPaper && nextPaper) {
        previousPaper.innerHTML += "<br>" + nextPaper.innerHTML.trim();
        nextPaper.remove();
      }

      updateWordCount();
    }
  }
}

// Trigger image upload
function triggerImageUpload() {
  const imageUploader = document.getElementById("imageUploader");
  imageUploader.click();
}

// Handle image uploaded
function handleImageUpload(event) {
  const limit = document.querySelector(".limit");
  const file = event.target.files[0];

  if (file && limit) {
    const reader = new FileReader();

    reader.readAsDataURL(file);

    reader.onload = function (e) {
      const uniqueImageId = Date.now();

      const currentTextLength = parseInt(
        limit.textContent.split(" / ")[0].replace(",", "")
      );
      const MaxTextLength = parseInt(
        limit.textContent.split(" / ")[1].replace(",", "")
      );

      const totalLength =
        currentTextLength + uniqueImageId.toString().length + 10;

      if (totalLength <= MaxTextLength) {
        const imageContainer = document.createElement("div");
        imageContainer.className = "image-container";

        const img = document.createElement("img");
        img.src = e.target.result;
        img.alt = "Uploaded Image";
        img.id = uniqueImageId.toString();

        const deleteIcon = document.createElement("div");
        deleteIcon.className = "delete-icon";
        deleteIcon.textContent = "×";
        deleteIcon.onclick = () => deleteImage(uniqueImageId.toString());

        imageContainer.appendChild(img);
        imageContainer.appendChild(deleteIcon);

        const notesDiv = document.getElementById("notes");
        notesDiv.appendChild(imageContainer);

        const newPaper = document.createElement("div");
        newPaper.className = "paper";
        newPaper.contentEditable = "true";
        newPaper.oninput = updateWordCount;

        notesDiv.appendChild(newPaper);

        document.getElementById("imageUploader").value = "";

        updateWordCount();
      } else {
        new swal(
          "Error",
          "Total length of note content and images exceeds the 60000 character limit!",
          "error"
        );
      }
    };
  } else {
    console.log("file not found.");
  }
}

// Save Data and replace checklist IDs
async function SaveData(save = false) {
  const titleField = document.getElementById("editable");
  const notesContainer = document.getElementById("notes");
  const papers = notesContainer.children;
  let noteContent = "";
  let newImages = [];
  let newChecklists = [];

  Array.from(papers).forEach((item) => {
    if (item.classList.contains("paper")) {
      let notescontents = item?.innerHTML
        ?.replaceAll("<div><br></div>", "\n")
        .replaceAll("<br>", "\n")
        .replaceAll("</div>", "\n")
        .replaceAll("<div>", "");
      if (notescontents.length > 0) {
        noteContent += notescontents;
      }
    } else if (item.classList.contains("image-container")) {
      const img = item.querySelector("img");
      if (img) {
        if (save) {
          newImages.push({
            id: `${img.id}`,
            image: img.src,
          });
        }
        noteContent += `{[image:${img.id}]}`;
      }
    } else if (item.classList.contains("checklist")) {
      let checklistId = item.id;
      if (save) {
        let checklistItems = [];
        item.querySelectorAll(".checklist-item").forEach((checkItem) => {
          const input = checkItem.querySelector("input");
          const checked = input.classList.contains("checked");
          checklistItems.push({
            checked,
            content: input.value,
          });
        });
        newChecklists.push({ id: checklistId, lists: checklistItems });
      }
      noteContent += `{[check:${checklistId}]}`;
    }
  });

  // Call API to save data
  const SavedOrNot = document.getElementById("SavedOrNot");
  if (save) {
    await callApi({
      action: "updateNote",
      params: {
        note_id: NotesData?.id,
        new_note_name: titleField.innerText,
        new_note_content: noteContent,
        new_images: newImages,
        new_checklists: newChecklists,
      },
    });

    NotesData = {
      content: noteContent,
      name: titleField.innerText,
    };

    SavedOrNot.textContent = "Saved";
  } else {
    if (
      noteContent !== NotesData?.content ||
      titleField.innerText !== NotesData?.name
    ) {
      SavedOrNot.textContent = "Not Saved";
    } else {
      SavedOrNot.textContent = "Saved";
    }
  }
}

// Handle Word Count
function updateWordCount() {
  const limit = document.querySelector(".limit");
  const maxChars = 60000;

  // paper
  let textLength = Array.from(document.querySelectorAll(".paper")).reduce(
    (acc, paper) => {
      if (acc > 0) {
        acc += 2;
      }
      return acc + paper?.innerText?.trim().length;
    },
    0
  );

  // image
  document.querySelectorAll(".image-container img").forEach((img) => {
    const imageIdLength = img.id.length;
    textLength += imageIdLength;
    textLength += 10;
  });

  document.querySelectorAll(".checklist").forEach((checklist) => {
    textLength += checklist?.id?.length ?? 0;
    textLength += 10;
  });

  if (textLength > maxChars) {
    document.querySelectorAll(".paper").forEach((paper) => {
      paper.innerText = paper.innerText.slice(0, maxChars);
    });
  }

  limit.textContent = `${textLength} / ${maxChars}`;
  SaveData(false);
}

// Tongle Menu
function toggleMenu() {
  const menu = document.getElementById("menu");
  menu.classList.toggle("show");
}

// Tongle Menu
document.addEventListener("click", function (event) {
  const menu = document.getElementById("menu");
  const menuIcon = document.querySelector(".menu-icon");
  if (!menu.contains(event.target) && !menuIcon.contains(event.target)) {
    menu.classList.remove("show");
  }
});

// Checklist Create
function createChecklist() {
  const notesContainer = document.getElementById("notes");
  const checklistContainer = createElement("div", ["checklist"]);
  const checklistItemId = Date.now();
  checklistContainer.setAttribute("id", checklistItemId);
  notesContainer.appendChild(checklistContainer);

  const paper = createElement("div", ["paper"]);
  paper.addEventListener("input", updateWordCount);
  paper.setAttribute("contenteditable", "true");
  notesContainer.appendChild(paper);

  createChecklistItem(checklistContainer);
}

function createElement(tag, classList = []) {
  const element = document.createElement(tag);
  if (classList.length) {
    element.classList.add(...classList);
  }
  return element;
}

function createChecklistItem(checklistContainer) {
  const checklistItem = createElement("div", ["checklist-item"]);

  checklistItem.innerHTML = `
      <div class="checkbox" onclick="toggleCheckbox(this)">✔</div>
      <input type="text" placeholder="Type your task..." onkeydown="checkEnter(event, this)">
      <button class="move-btn" onclick="moveUp(this)">⬆</button>
      <button class="move-btn" onclick="moveDown(this)">⬇</button>
      <button class="remove-btn" onclick="removeItem(this)">X</button>`;

  checklistContainer.appendChild(checklistItem);
  checklistItem.querySelector("input").focus();
  updateWordCount();
}

function toggleCheckbox(checkbox) {
  const input = checkbox.nextElementSibling;
  input.classList.toggle("checked");
}

function checkEnter(event, input) {
  if (event.key === "Enter") {
    event.preventDefault();
    createChecklistItem(input.parentElement.parentElement);
  }
}

function moveUp(button) {
  const item = button.parentElement;
  const prevItem = item.previousElementSibling;
  if (prevItem) {
    item.parentElement.insertBefore(item, prevItem);
  }
}

function moveDown(button) {
  const item = button.parentElement;
  const nextItem = item.nextElementSibling;
  if (nextItem) {
    item.parentElement.insertBefore(nextItem, item);
  }
}

function removeItem(button) {
  const checklistItem = button.parentElement;
  const checklistContainer = checklistItem.closest(".checklist");

  if (checklistContainer && checklistContainer.children.length - 1 === 0) {
    const previousPaper = checklistContainer.previousElementSibling;
    const nextPaper = checklistContainer.nextElementSibling;
    checklistContainer.remove();
    if (previousPaper && nextPaper) {
      previousPaper.innerHTML += nextPaper.innerHTML.trim();
      nextPaper.remove();
    }
  } else {
    checklistItem.remove();
  }
  updateWordCount();
}

function goback() {
  window.location.href = "./";
}

function HandleName(element) {
  const MaxLength = 100;
  if (element.innerText.length > MaxLength) {
    element.innerText = element.innerText.substring(0, MaxLength);
  }
  SaveData(false);
}

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

function loadSharingScreen() {
  const sharingModal = document.createElement("div");
  sharingModal.id = "sharingModal";
  sharingModal.classList.add("sharing-modal");

  sharingModal.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-title">Notes Sharing</span>
        <button class="close-btn" onclick="closeSharingScreen()">×</button>
      </div>
      <div class="modal-body">
        <div>
          <label for="sharing-toggle">Sharing: </label>
          <input type="checkbox" id="sharing-toggle" onchange="toggleSharing(this)">
          <span id="sharing-status">OFF</span>
        </div>
        <div id="sharing-link-container" style="display: none;">
          <p>Link to Note:</p>
          <input type="text" id="note-link" value="" readonly />
          <button onclick="copyLink()">Copy Link</button>
          <div>
            <label for="everyone-toggle">Shared with everyone: </label>
            <input type="checkbox" id="everyone-toggle" onchange="toggleEveryone(this)">
          </div>
        </div>
        <div id="shared-with-container" style="display: none;">
          <div>
            <label for="email-input">Add Email:</label>
            <input type="email" id="email-input" placeholder="Enter email" required>
            <button onclick="addEmail()">Add</button>
          </div>
          <table id="shared-emails">
            <tr>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </table>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(sharingModal);

  window.addEventListener("click", function (event) {
    if (event.target === sharingModal) {
      closeSharingScreen();
    }
  });

  if (NotesData?.sharing_info?.id) {
    document.getElementById("sharing-toggle").checked = true;
    document.getElementById("sharing-status").textContent = "ON";

    document.getElementById("sharing-link-container").style.display = "block";

    const path = window.location.pathname.split("/").slice(0, -1).join("/");
    const baseUrl = `${window.location.origin}${path}/shared.html?id=${NotesData.sharing_info.id}`;

    document.getElementById("note-link").value = baseUrl;

    document.getElementById("everyone-toggle").checked = false;
    document.getElementById("shared-with-container").style.display = "block";

    const sharedEmails = NotesData.sharing_info.shared_with_emails;
    const emailTable = document.getElementById("shared-emails");
    sharedEmails.forEach((email) => {
      const row = document.createElement("tr");
      row.innerHTML = `
          <td>${email}</td>
          <td><button onclick="removeEmail(this)">Delete</button></td>
        `;
      emailTable.appendChild(row);
    });

    if (NotesData.sharing_info.shared_with_all) {
      document.getElementById("everyone-toggle").checked = true;
      document.getElementById("shared-with-container").style.display = "none";
    }
  } else {
    document.getElementById("sharing-toggle").checked = false;
    document.getElementById("sharing-status").textContent = "OFF";
    document.getElementById("sharing-link-container").style.display = "none";
    document.getElementById("shared-with-container").style.display = "none";
  }
}

function closeSharingScreen() {
  const sharingModal = document.getElementById("sharingModal");
  if (sharingModal) {
    sharingModal.remove();
  }
}

async function toggleSharing(input) {
  const status = document.getElementById("sharing-status");
  const linkContainer = document.getElementById("sharing-link-container");
  const emailContainer = document.getElementById("shared-with-container");

  if (input.checked) {
    status.textContent = "ON";
    linkContainer.style.display = "block";
    if (!document.getElementById("everyone-toggle").checked) {
      emailContainer.style.display = "block";
    }

    if (!NotesData?.sharing_info?.id) {
      NotesData.sharing_info.id = Date.now().toString();
      await callApi({
        action: "shareNoteAdd",
        params: {
          id: NotesData.sharing_info.id,
          note_id: NotesData?.id,
        },
      });

      const path = window.location.pathname.split("/").slice(0, -1).join("/");
      const baseUrl = `${window.location.origin}${path}/shared.html?id=${NotesData.sharing_info.id}`;
      document.getElementById("note-link").value = baseUrl;
    }
  } else {
    status.textContent = "OFF";
    linkContainer.style.display = "none";
    emailContainer.style.display = "none";

    if (
      await callApi({
        action: "ShareNoteRemove",
        params: {
          id: NotesData?.sharing_info?.id,
          note_id: NotesData?.id,
        },
      })
    ) {
      NotesData.sharing_info = {};
    }
  }
}

function copyLink() {
  const linkField = document.getElementById("note-link");
  linkField.select();
  linkField.setSelectionRange(0, 99999);
  document.execCommand("copy");
  new swal("link copied", "link copied to clipboard!", "info");
}

async function toggleEveryone(input) {
  const emailContainer = document.getElementById("shared-with-container");
  const isVisibleToAll = input.checked;

  if (isVisibleToAll) {
    emailContainer.style.display = "none";
  } else {
    const sharingToggle = document.getElementById("sharing-toggle");
    if (sharingToggle.checked) {
      emailContainer.style.display = "block";
    }
  }

  await callApi({
    action: "shareNoteVisibility",
    params: {
      id: NotesData.sharing_info.id,
      note_id: NotesData?.id,
      visibility: isVisibleToAll,
    },
  });
}

async function addEmail() {
  const emailInput = document.getElementById("email-input");
  const emailValue = emailInput.value.trim();
  if (emailValue && emailInput.checkValidity()) {
    if (
      await callApi({
        action: "shareNoteUserAdd",
        params: {
          id: NotesData?.sharing_info?.id,
          note_id: NotesData?.id,
          email: emailValue,
        },
      })
    ) {
      const table = document.getElementById("shared-emails");
      const row = document.createElement("tr");
      row.innerHTML = `
      <td>${emailValue}</td>
      <td><button onclick="removeEmail(this)">Delete</button></td>
    `;
      table.appendChild(row);
      emailInput.value = "";
    }
  } else {
    new swal("invalid email!", "Please enter a valid email.", "error");
  }
}

async function removeEmail(button) {
  const row = button.closest("tr");
  if (row) {
    const email = row.querySelector("td").textContent;
    const response = await callApi({
      action: "shareNoteUserRemove",
      params: {
        id: NotesData?.sharing_info?.id,
        note_id: NotesData?.id,
        email: email,
      },
    });
    if (response?.status === "success") {
      row.remove();
    }
  }
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
