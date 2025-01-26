<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
    />
    <link rel="stylesheet" href="./css/note.css" />
    <title>Edit Note</title>
  </head>
  <body>
    <script>
      let noteId;

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
          let noteContent = note?.data?.note_content;
          let HtmlData = `<div class="paper" contenteditable="true" id="paper">${noteContent}`;

          // notes length
          let noteLength =  noteContent ? noteContent .length : 0;
          
          // replacing all \n with new div
          HtmlData = HtmlData.replaceAll(/\n/g, '<br>');

          // replacing images if there are images
          const images = note?.data?.note_images || [];
          images.forEach(image => {
            const placeholder = `{[${image.split(".")[0]}]}`;
            const imageHtml = `
            </div>
            <div class="image-container">
              <img src="./images/${image}" alt="${image}" id="${image}">
              <div class="delete-icon" onclick="deleteImage('${image}')">×</div>
            </div>
            <div class="paper" contenteditable="true" id="paper">`;
            HtmlData = HtmlData.replace(placeholder, imageHtml)
          });

          // closing the div
          HtmlData += "</div>"

          document.body.innerHTML = `
          <div class="top">
            <i class="fas fa-arrow-left back" onclick="goback()"></i>
            <div contenteditable="true" id="editable">
              ${note?.data?.note_name ? note?.data?.note_name : "Untitled Note"}
            </div>
            <i class="fas fa-image addimage" onclick="triggerImageUpload()"></i>
          </div>
          
          <div id="notes">
            ${HtmlData}
          </div>
          
          <input type="file" id="imageUploader" accept="image/*" style="display: none;" onchange="handleImageUpload(event)" />

          <div class="limit">${noteLength} / 60000</div>`;

          // Title max 100 characters
          document
            .getElementById("editable")
            .addEventListener("input", function () {
              const maxLength = 100;
              if (this.innerText.length > maxLength) {
                this.innerText = this.innerText.substring(0, maxLength);
              }
            });

          // Note character count
          const paper = document.querySelector(".paper");
          const limit = document.querySelector(".limit");
          const maxChars = 60000;

          paper.addEventListener("input", updateWordCount);
          updateWordCount();

          // Save notes on Ctrl+S
          document.addEventListener("keydown", async function (event) {
            if (event.ctrlKey && event.key.toLowerCase() === "s") {
              event.preventDefault();
              let results = await SaveData();
              if (results && results?.status === "success") {
                // Alert saved
              } else {
                // TODO: Alert error
              }
            }
          });
          
          // Trigger file input
          window.triggerImageUpload = function () {
            document.getElementById("imageUploader").click();
          };
          
          // Handle image upload
          window.handleImageUpload = function (event) {
            const file = event.target.files[0];
            if (file) {
              const reader = new FileReader();
              reader.onload = function (e) {
                const uniqueImageId = Date.now();
                
                const currentTextLength = parseInt(
                  limit.textContent.split(" / ")[0].replace(",", "")
                );
                const MaxTextLength = parseInt(
                  limit.textContent.split(" / ")[1].replace(",", "")
                );
                
                const totalLength = currentTextLength + noteId.length + uniqueImageId.toString().length + 8;
                
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
                  deleteIcon.onclick = () => imageContainer.remove();
                  
                  imageContainer.appendChild(img);
                  imageContainer.appendChild(deleteIcon);
                  
                  const notesDiv = document.getElementById("notes");
                  notesDiv.appendChild(imageContainer);
                  
                  const newPaper = document.createElement("div");
                  newPaper.className = "paper";
                  newPaper.contentEditable = "true";
                  
                  notesDiv.appendChild(newPaper);
                  
                  document.getElementById("imageUploader").value = "";
                  
                  limit.textContent = `${totalLength} / ${MaxTextLength}`;
                } else {
                  new swal(
                    "Error",
                    "Total length of note content and images exceeds the 60000 character limit!",
                    "error"
                  );
                }
              };
              reader.readAsDataURL(file);
            }
          };


        } else {
          window.location = "./404.php"
        }
      }

      // Save Data
      async function SaveData() {
        const titleField = document.getElementById("editable");
        const notesContainer = document.getElementById("notes");
        const papers = notesContainer.children;
        let noteContent = "";
        let newImages = [];

        Array.from(papers).forEach((item, index) => {
          if (item.classList.contains("paper")) {
            let notescontents = item?.innerText?.trim()
            if (notescontents.length > 0){
              if (noteContent.length > 0 && !/\{\[[^}]+-[^}]+\]\}/.test(noteContent)) {
                noteContent += "\n";
              }
              noteContent += notescontents;
            }
          } else if (item.classList.contains("image-container")) {
            const img = item.querySelector("img");
            if (img) {
              if (img && img?.alt && img?.alt !== "Uploaded Image"){
                const imagePlaceholder = `{[${img.id.replace(".png", "")}]}`;
                newImages.push({
                  image_name: img.id,
                  image_data: null,  
                });
                noteContent += ` ${imagePlaceholder} `;
              } else {
                const imagePlaceholder = `{[${noteId}-${img.id}]}`;
                newImages.push({
                  image_name: `${noteId}-${img.id}`,
                  image_data: img.src.split(',')[1],  
                });
                noteContent += ` ${imagePlaceholder} `;
              }
            }
          }
        });
        
        await callApi({
          action: "updateNote",
          params: {
            note_id: noteId,
            new_note_name: titleField.innerText.trim(),
            new_note_content: noteContent,
            new_images: newImages,
          },
        });
      }

      // upload image
      function triggerImageUpload() {
        const imageUploader = document.getElementById("imageUploader");
        imageUploader.click();
      }
      
      // handle image uploaded
      function handleImageUpload(event) {
        const file = event.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function (e) {
            const paper = document.getElementById("paper");

            const container = document.createElement("div");
            container.className = "image-container";
            container.contentEditable = "false"; 
            
            const img = document.createElement("img");
            img.src = e.target.result;
            img.alt = "Uploaded Image";
            
            const deleteIcon = document.createElement("div");
            deleteIcon.className = "delete-icon";
            deleteIcon.textContent = "×";
            deleteIcon.onclick = function () {
              container.remove();
            };
            container.appendChild(img);
            container.appendChild(deleteIcon);
            paper.appendChild(container);
          };
          reader.readAsDataURL(file);
        }
      }

      // handle image delete
      window.deleteImage = function (imageId) {
        const imageElement = document.getElementById(imageId);
        if (imageElement) {
          const imageContainer = imageElement.closest('.image-container');
          if (imageContainer) {
            const previousPaper = imageContainer.previousElementSibling;
            const nextPaper = imageContainer.nextElementSibling;
            imageContainer.remove();
            
            if (previousPaper && nextPaper) {
              previousPaper.innerHTML += '<br>' + nextPaper.innerHTML.trim();
              nextPaper.remove();
            }
          }
        }
      };

      // Handle Update Word Count
      window.updateWordCount = function () {
        const limit = document.querySelector(".limit");
        const maxChars = 60000;
        
        let textLength = Array.from(document.querySelectorAll(".paper")).reduce((acc, paper) => {
          if (acc > 0) {
            acc += 2;
          }
          return acc + paper?.innerText?.trim().length;
        }, 0);
        
        if (textLength === 0) {
          textLength = 1;
        }
        
        document.querySelectorAll(".image-container img").forEach((img) => {
          const imageIdLength = img.id.length;
          textLength += imageIdLength;
          textLength += 7;
          textLength += noteId.length;
        });
        
        if (textLength > maxChars) {
          document.querySelectorAll(".paper").forEach((paper) => {
            paper.innerText = paper.innerText.slice(0, maxChars);
          });
        }
        limit.textContent = `${textLength} / ${maxChars}`;
      };

      // Go Back
      async function goback() {
        await SaveData();
        window.location = "./";
      }

      // API helper function
      async function callApi(data) {
        const data = await fetch("./api.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(data),
        }).then((response) => response.json());

        if (data?.redirect){
          window.location.href = `${data?.redirect}`;
        }

        return data
      }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  </body>
</html>
