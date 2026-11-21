<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/style.css?v=7">
    </head>

    <body>
        <header>
            <nav class="navbar">
                <a href="home.php" class="nav-logo"> <h2 class="logo-text"> Casa Adelaida </h2></a>

                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="home.php" class="nav-link" aria-current="page"> Home </a> </li>
                    
                    <li class="nav-item">
                        <a href="about.php" class="nav-link"> About </a> </li>

                    <li class="nav-item">
                        <a href="book.php" class="nav-link"> Book Now </a> </li>
                </ul>
            </nav>
        </header>
    
    <section class="hero-section">
        <div class="container">
            <div class="hero-details">
                <h2 class="title"> LetStayEase </h2>
                <h2 class="title2"> Casa Adelaida </h2>
                <h3 class="subtitle"> Come and make your unforgettable memories here in Casa Adelaida. </h3>
                <a href="book.php"> <button class="button"> Book your slots now! </button></a>
            </div>

            <div class="carousel" aria-label="Casa Adelaida pool photos">
                <div class="carousel-track">
                    <figure class="carousel-slide">
                        <img src="img/big_pool.jpg" alt="Big pool at Casa Adelaida">
                    </figure>
                    <figure class="carousel-slide">
                        <img src="img/big_pool2.jpg" alt="Another view of the big pool">
                    </figure>
                    <figure class="carousel-slide">
                        <img src="img/small_pool.jpg" alt="Small pool at Casa Adelaida">
                    </figure>
                    <figure class="carousel-slide">
                        <img src="img/small_pool2.jpg" alt="Another view of the small pool">
                    </figure>
                </div>

                <div class="carousel-dots" aria-label="Choose a photo">
                    <button class="dot is-active" type="button" aria-label="Show photo 1" aria-current="true"></button>
                    <button class="dot" type="button" aria-label="Show photo 2"></button>
                    <button class="dot" type="button" aria-label="Show photo 3"></button>
                    <button class="dot" type="button" aria-label="Show photo 4"></button>
                </div>
            </div>
        </div>
    </section>

    <script src="js/page-transition.js?v=1" defer></script>
    <script src="js/home.js" defer></script>

    </body>
</html>