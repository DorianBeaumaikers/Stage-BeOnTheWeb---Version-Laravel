<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
</head>

<body class="antialiased">
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Navbar</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Users</a>
                    </li>
                </ul>
                <span class="navbar-text">
                    Welcome {{ Auth::user()->name }}
                </span>
                <span class="navbar-text ps-3">
                    <form action="{{ route('logout') }}" method="post">
                        @csrf

                        <button type="submit" class="btn btn-primary me-3">Logout</button>
                    </form>
                </span>
            </div>
        </div>
    </nav>
    <main class="container p-2">
        <strong>Database Connected: </strong>
        <?php
        try {
            \DB::connection()->getPDO();
            echo \DB::connection()->getDatabaseName();
            echo '<br>';
        } catch (\Exception $e) {
            echo $e;
        }
        if ($dossierToken) {
            echo 'Token récupérer <br>';
        }
        if ($dossierToken) {
            echo 'Token de dossier récupérer <br>';
        }
        if ($modifiedBuyBookings) {
            echo '<h2>Modified Buy Bookings</h2>';
            echo '<pre>';
            print_r($modifiedBuyBookings);
            echo '</pre>';
        }
        if ($modifiedSellBookings) {
            echo '<h2>Modified Sell Bookings</h2>';
            echo '<pre>';
            print_r($modifiedSellBookings);
            echo '</pre>';
        }
        ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>

</body>

</html>
