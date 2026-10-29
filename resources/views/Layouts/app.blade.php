<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title')</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

<link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
@include('school.partials.theme-head')
</head>

<body>
  <div class="container"><div class="legacy-theme">@include('school.partials.theme-picker')</div>
    <h1>@yield('title')</h1>
    @yield('content')
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous">
  </script>

  <!-- Sweet Alert 2 Script -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
      document.querySelectorAll('.btn-delete').forEach(button => {
      button.addEventListener('click', function (e) {
          let form = this.closest('form');
          Swal.fire({
              title: "Are you sure?",
              text: "This item will be permanently deleted.",
              icon: "warning",
              showCancelButton: true,
              confirmButtonColor: "#d33",
              cancelButtonColor: "#3085d6",
              confirmButtonText: "Yes, delete!"
          }).then((result) => {
              if (result.isConfirmed) { form.submit();}
          });
      });
  });
  </script>
  <!-- End of Sweet Alert 2 Script -->
</body>

</html>
