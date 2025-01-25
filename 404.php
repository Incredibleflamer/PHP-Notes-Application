<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>404 Not Found</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        background: #22232e;
        font-family: "Poppins", sans-serif;
      }
      a {
        text-decoration: none;
      }
      section {
        width: 100%;
      }
      .container {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: row;
        column-gap: 20px;
      }
      .container img {
        width: 420px;
      }
      .text {
        display: block;
        padding: 40px 40px;
        width: 450px;
        text-align: center;
      }
      .text h1 {
        color: #00c2cb;
        font-size: 35px;
        font-weight: 700;
        margin-bottom: 15px;
      }
      .text p {
        font-size: 15px;
        color: #00c2cb;
        margin-bottom: 15px;
        line-height: 1.5rem;
        margin-bottom: 15px;
      }
      </style>
  </head>
  <body>
    <section>
      <div class="container">
        <div class="text">
          <h1>Note Not Found</h1>
          <p><a href="./">Home</a> </p>
        </div>
        <div>
          <img class="image" src="./images/404.png" alt="" />
        </div>
      </div>
    </section>
  </body>
</html>
