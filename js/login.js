function showError(message) {
  const errorParagraph = document.getElementById("error");
  errorParagraph.textContent = message;
  errorParagraph.classList.remove("hidden");
}

const urlParams = new URLSearchParams(window.location.search);
const error = urlParams.get("error");
const ProcessType = urlParams.get("ProcessType");

if (error) {
  showError(error);
}

// Handle Signup form submission
const signupForm = document.getElementById("signupForm");
signupForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const username = document.getElementById("signupUsername").value;
  const email = document.getElementById("signupEmail").value;
  const password = document.getElementById("signupPassword").value;

  const data = await callApi({
    action: "signup",
    params: {
      user_id: Date.now().toString(),
      username: username,
      email: email,
      pass: password,
    },
  });
  document.getElementById("signupForm").reset();
  if (data?.status === "success") {
    window.location.href = "./";
  } else if (data?.message) {
    showError(data?.message);
  }
});

// Handle Login form submission
const loginForm = document.getElementById("loginForm");
loginForm.addEventListener("submit", async function (event) {
  event.preventDefault();

  const username = document.getElementById("loginUsername").value;
  const password = document.getElementById("loginPassword").value;

  const data = await callApi({
    action: "login",
    params: {
      username: username,
      pass: password,
    },
  });
  document.getElementById("loginForm").reset();
  if (data?.status === "success") {
    window.location.href = "./";
  } else if (data?.message) {
    showError(data?.message);
  }
});

// Function to send the request to the server
async function callApi(data) {
  return await fetch("./api.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  }).then((response) => response.json());
}

var currentPage =
  ProcessType === "signup" || ProcessType === "login" ? ProcessType : "signup";

if (currentPage === "login") {
  const chk = document.getElementById("chk");
  chk.click();
}
