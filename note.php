<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notes - Todo</title>
    <link rel="stylesheet" href="./css/note.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
    />
  </head>
  <body>
    <script>
      try {
        let noteId = null;
        let NotesString = "";

        LoadNote();

        async function LoadNote() {
          const params = new URLSearchParams(window.location.search);
          noteId = params.get("id");

          const note = await callApi({
            action: "getNote",
            params: {
              note_id: noteId,
            },
          });

          if (note && note?.status === "success") {
            NotesString = note?.data?.note_content;
            let noteContent = note?.data?.note_content;
            let HtmlData = `<div class="paper" contenteditable="true" id="paper" oninput="updateWordCount()">${noteContent}`

            // replacing all \n with new div
            HtmlData = HtmlData.replaceAll(/\n/g, '<br>');

            const images = note?.data?.note_images || [];
            images.forEach((image) => {
              HtmlData = addImage(HtmlData, image);
            });

            note?.data?.checklist.forEach((list) => {
              HtmlData = addChecklist(HtmlData, list);
            });

            HtmlData += "</div>";

            document.body.innerHTML = `

          <div class="top">
            <i class="fas fa-arrow-left back" onclick="goback()"></i>
            <div contenteditable="true" id="editable">${
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

            document
              .getElementById("editable")
              .addEventListener("input", function () {
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
            window.location = "./404.php";
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
                deleteIcon.onclick = () =>
                  deleteImage(uniqueImageId.toString());

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
              let notescontents = item?.innerHTML?.replaceAll("<div> <br> </div>", "\n")
              if (notescontents.length > 0){
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
                item
                  .querySelectorAll(".checklist-item")
                  .forEach((checkItem) => {
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
                note_id: noteId,
                new_note_name: titleField.innerText,
                new_note_content: noteContent,
                new_images: newImages,
                new_checklists: newChecklists,
              },
            });

            NotesString = noteContent;
            SavedOrNot.textContent = "Saved";
          } else {
            if (noteContent !== NotesString) {
              SavedOrNot.textContent = "Not Saved";
            } else {
              SavedOrNot.textContent = "Saved";
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
          }

          return data;
        }

        function updateWordCount() {
          const limit = document.querySelector(".limit");
          const maxChars = 60000;

          // paper
          let textLength = Array.from(
            document.querySelectorAll(".paper")
          ).reduce((acc, paper) => {
            if (acc > 0) {
              acc += 2;
            }
            return acc + paper?.innerText?.trim().length;
          }, 0);

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

        function toggleMenu() {
          const menu = document.getElementById("menu");
          menu.classList.toggle("show");
        }

        document.addEventListener("click", function (event) {
          const menu = document.getElementById("menu");
          const menuIcon = document.querySelector(".menu-icon");
          if (
            !menu.contains(event.target) &&
            !menuIcon.contains(event.target)
          ) {
            menu.classList.remove("show");
          }
        });

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

          if (
            checklistContainer &&
            checklistContainer.children.length - 1 === 0
          ) {
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
          window.location.href = "index.php";
        }
      } catch (err) {
        console.log(err);
      }
    </script>
  </body>
</html>
