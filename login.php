<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Sign Up</title>
    <link rel="stylesheet" href="./css/login.css" />
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@500&display=swap" rel="stylesheet" />
</head>
<body>
    <section>
        <p id="error" class="hidden error"></p>
    </section>

    <div class="main">
        <input type="checkbox" id="chk" aria-hidden="true" />
        <div class="signup">
            <form id="signupForm" method="POST">
                <label for="chk" aria-hidden="true">Sign up</label>
                <input type="text" name="username" id="signupUsername" placeholder="Username" required="" />
                <input type="email" name="email" id="signupEmail" placeholder="Email" required="" />
                <input type="password" name="password" id="signupPassword" placeholder="Password" required="" />
                <button type="submit">Sign up</button>
            </form>
        </div>

        <div class="login">
            <form id="loginForm" method="POST">
                <label for="chk" aria-hidden="true">Login</label>
                <input type="text" name="username" id="loginUsername" placeholder="Username" required="" />
                <input type="password" name="password" id="loginPassword" placeholder="Password" required="" />
                <button type="submit">Login</button>
            </form>
        </div>
    </div>

    <script>
        function showError(message) {
            const errorParagraph = document.getElementById("error");
            errorParagraph.textContent = message;
            errorParagraph.classList.remove("hidden");
        }

        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get("error");
        const ProcessType = urlParams.get("ProcessType")

        if (error) {
            showError(error);
        }

        // Handle Signup form submission
        const signupForm = document.getElementById('signupForm');
        signupForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const username = document.getElementById('signupUsername').value;
            const email = document.getElementById('signupEmail').value;
            const password = document.getElementById('signupPassword').value;

            const data = await callApi({
                action: 'signup',
                params: {
                    user_id: Date.now().toString(),
                    username: username,
                    email: email,
                    pass: password
                }
            });
            document.getElementById('signupForm').reset();
            if (data?.status === "success"){
                window.location.href = "./"
            } else if (data?.message){
                showError(data?.message)
            }
        });

        // Handle Login form submission
        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const username = document.getElementById('loginUsername').value;
            const password = document.getElementById('loginPassword').value;

            const data = await callApi({
                action: 'login',
                params: {
                    username: username,
                    pass: password
                }
            });
            document.getElementById('loginForm').reset();  
            if (data?.status === "success"){
                window.location.href = "./"
            } else if (data?.message){
                showError(data?.message)
            }
        });

        // Function to send the request to the server
        async function callApi(data) {
            return await fetch("./api.php", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)  
            }).then(response => response.json());
        }

        var currentPage = ProcessType === "signup" || ProcessType === "login" ? ProcessType : "signup";

        if (currentPage === "login") {
            const chk = document.getElementById("chk");
            chk.click();
        }
    </script>
</body>
</html>
