<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
    />
    <link rel="stylesheet" href="./css/style.css" />
    <title>Notes Application</title>
</head>
<body>
    <h2 id="greeting">Hello!</h2>
    <div class="container">
        <button class="btn_add"><i class="fas fa-pencil"></i> Add Note</button>
        <div class="sort-dropdown">
            <select id="sortOptions">
                <option value="" selected>Sort: Default</option>
                <option value="asc">Sort: Ascending</option>
                <option value="desc">Sort: Descending</option>
            </select>
        </div>
        <div class="search-container">
            <button class="btn_search"><i class="fas fa-search"></i> Search</button>
            <input type="text" class="search-bar" placeholder="Type to search..." />
        </div>
    </div>
    <div class="notes_container" id="all"></div>
    <div class="notes_container hidden" id="notes_search"></div>
    <script>
    const notesContainer = document.querySelector("#all");
    const SearchNotesContainer = document.querySelector("#notes_search");
    const greeting = document.querySelector("#greeting");

    const sortOptions = document.querySelector("#sortOptions");
    let LastSort = sortOptions.value;

    sortOptions.addEventListener("change", async (e) => {
        LastSort = e.target.value;
        notesContainer.innerHTML = "";
        await loadNotes(LastSort);
    });

    loadNotes(LastSort);

    // load notes
    async function loadNotes(sortOrder = "") {
        try {
            const notes = await callApi({ action: 'getAllNotes', params: {
                order: sortOrder
            } }) || [];

            if (notes && notes?.username){
                greeting.textContent = `Hello ${notes?.username.trim().toLowerCase().replace(/^\w/, (c) => c.toUpperCase())}!`;
            }

            if (notes && notes?.data && notes?.data?.length > 0) {
                // Rendering all notes
                notes?.data.forEach((note) => {
                    const noteName = note?.note_name?.length > 50 
                    ? `${note?.note_name.slice(0, 50)}...` 
                    : note?.note_name || "Untitled Note";
                    
                    
                    const noteContent = note?.note?.length > 150 
                    ? `${note?.note.slice(0, 150)}...` 
                    : note?.note || "No Content...";
                    
                    AddNoteDiv(note?.note_id, noteName, noteContent, note?.pin, "all");
                });
            }
        } catch (error) {
            console.error('Error fetching notes:', error);
        }
    }


    // Add new note button
    document.querySelector(".btn_add").addEventListener("click", async () => {
        const id = Date.now().toString();
        let AddedToDB = await callApi({action: "addNote", params: {
            note_id: id
        }})
        if (AddedToDB && AddedToDB?.status === "success"){
            window.location.href = `./note.php?id=${id}`;
        } else {
            // TODO : SHOW ERROR
        }
    });

    // adding notes div
    function AddNoteDiv(noteid , notetitle, notecontent, pinned , Type){
        if (!noteid) return;
        const noteElement = document.createElement("div");
        noteElement.classList.add("note-wrapper");
        noteElement.innerHTML = `
        <div class="operations">
        <div class="title">${notetitle}</div>
        <button class="delete operations_buttons fas fa-trash-alt "onclick="notedelete(event , ${noteid})"></button>
        <button class="${pinned ? "pinned" : "pin"} operations_buttons fas fa-thumbtack" onclick="notepin(event , ${noteid})"></button>
        </div>
        <div class="main">${notecontent}</div>
        `;
        
        noteElement.addEventListener("click", (e) => {
            if (!e.target.classList.contains("operations_buttons")) {
                window.location.href = `./note.php?id=${noteid}`;
            }
        });

        if (Type === "all"){
            notesContainer.appendChild(noteElement);
        } else if (Type === "search") {
            SearchNotesContainer.appendChild(noteElement)
        }
    }

    // Pin Note
    async function notepin(event , noteId) {
        event.stopPropagation();

        const NotePinned = await callApi({
            action: "pinNote",
            params: {
                note_id: noteId
            }
        })

        if (NotePinned?.status === "success"){
            notesContainer.innerHTML = "";
            await loadNotes(LastSort);
        }
    }
    
    // deleting notes button
    async function notedelete(event, noteId) {
        event.stopPropagation();

        const deleted_note = await callApi({
            action: "removeNote",
            params: {
                note_id: noteId
            }
        });
        
        if (deleted_note && deleted_note?.status === "success"){
            const noteElement = event.target.closest(".note-wrapper");
            if (noteElement) {
                noteElement.remove();
            }
        } else {
            // TODO : ALERT
        }
    }

    // search
    const btnSearch = document.querySelector(".btn_search");
    const searchBar = document.querySelector(".search-bar");

    const clearButton = document.createElement("button");
    clearButton.classList.add("clear-btn");
    clearButton.innerHTML = "&times;";
    clearButton.style.display = "none";
    document.querySelector(".search-container").appendChild(clearButton);
    
    btnSearch.addEventListener("click", () => {
        searchBar.classList.toggle("active");
        btnSearch.classList.toggle("hidden");
        searchBar.focus();
    });

    document.addEventListener("click", (e) => {
        if (!btnSearch.contains(e.target) && !searchBar.contains(e.target)) {
            if (searchBar.value.trim() === "") {
                searchBar.classList.remove("active");
                btnSearch.classList.remove("hidden");
            }
        }
    });
    
    searchBar.addEventListener("click", (e) => {
        e.stopPropagation();
    });
    
    searchBar.addEventListener("keydown", async (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            if (searchBar.value.trim().length > 0){
                // search the query
                const Found_Notes = await callApi({
                    action: "findNote",
                    params: {
                        word: searchBar.value.trim()
                    }
                });
                // clearing old notes
                SearchNotesContainer.innerHTML = "";

                if (Found_Notes && Found_Notes?.length > 0){
                    // loading notes
                    Found_Notes.forEach((note) => {
                        const noteName = note?.note_name?.length > 50 
                        ? `${note?.note_name.slice(0, 50)}...` 
                        : note?.note_name || "Untitled Note";

                        const noteContent = note?.note?.length > 150 
                        ? `${note?.note.slice(0, 150)}...` 
                        : note?.note || "No Content...";
                        
                        AddNoteDiv(note?.note_id, noteName, noteContent, note?.pin , "search");
                    });
                }

                // show the results & hide all notes
                notesContainer.classList.add("hidden")
                notesContainer.classList.remove("notes_container")
                SearchNotesContainer.classList.remove("hidden")
                SearchNotesContainer.classList.add("notes_container")
            }
            searchBar.blur();
        }
    });
    
    clearButton.addEventListener("click", () => {
        searchBar.value = "";
        searchBar.focus();
        clearButton.style.display = "none";
        // show the notes & hide search notes and clear it
        notesContainer.classList.remove("hidden");
        notesContainer.classList.add("notes_container")
        SearchNotesContainer.innerHTML = "";
        SearchNotesContainer.classList.add("hidden");
        SearchNotesContainer.classList.remove("notes_container")
    });
    
    searchBar.addEventListener("input", () => {
        if (searchBar.value.trim() !== "") {
          clearButton.style.display = "block";
        } else {
          clearButton.style.display = "none";
          notesContainer.classList.remove("hidden")
          notesContainer.classList.add("notes_container")
          SearchNotesContainer.classList.add("hidden")
          SearchNotesContainer.classList.remove("notes_container")
        }
    });

    // function for making api calls
    async function callApi(data) {
        const Responsedata = await fetch("./api.php", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)  
        }).then(response => response.json());

        if (Responsedata?.redirect){
            window.location.href = `${Responsedata?.redirect}`;
        }

        return Responsedata;
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>