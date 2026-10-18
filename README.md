<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Fotos de perfil

La API recibe la foto autenticada en `POST /api/user/image` como multipart/form-data con el campo `image` (JPG, PNG o WebP; máximo 5 MB). El binario se guarda en MongoDB, en la colección `profile_images`; `GET /api/user` devuelve `image_url` cuando el usuario ya tiene una foto. La URL es temporal y firmada. `DELETE /api/user/image` elimina la foto del usuario autenticado.

Configura `MONGO_HOST`, `MONGO_PORT`, `MONGO_DATABASE`, `MONGO_USERNAME` y `MONGO_PASSWORD` en el entorno de la API. `MONGO_AUTH_SOURCE` es opcional y por defecto usa `MONGO_DATABASE`. En Docker, la imagen PHP ya incluye la extensión `mongodb`; si ejecutas Laravel directamente en Windows, instala y habilita esa extensión para la versión de PHP utilizada.

## Cambio de contraseña

`PUT /api/user/password` requiere un token Bearer de Sanctum y recibe `current_password`, `password` y `password_confirmation` como JSON. La nueva contraseña debe tener al menos 8 caracteres y coincidir con su confirmación.

## Integración con la aplicación web

Las plantillas y rutas de las pantallas viven en el proyecto `web`. Este proyecto API entrega los datos y procesa las operaciones que esas pantallas consumen; no es necesario duplicar las vistas Blade en la API.

| Pantalla en web | Operación API |
|---|---|
| Inicio de sesión | `POST /api/login` |
| Registro | `GET /api/carreras`, `POST /api/register` |
| Perfil y panel | `GET /api/user` |
| Fotografía de perfil | `POST /api/user/image`, `DELETE /api/user/image` |
| Seguridad de la cuenta | `PUT /api/user/password` |
| Cierre de sesión | `POST /api/logout` |
| Solicitudes de tutoría | `GET /api/tutoring/options`, `POST /api/tutoring/requests`, `GET /api/tutoring/requests`, `PATCH /api/tutoring/requests/{id}/status`, `POST /api/tutoring/requests/{id}/rating` |

Configura `API_BASE_URL` en el entorno del proyecto web con la URL base de esta API, accesible desde el navegador. Por ejemplo, para desarrollo local: `http://127.0.0.1:8001`. En la API, `CORS_ALLOWED_ORIGINS` acepta una lista de orígenes web separada por comas; si no se configura, se permiten los orígenes locales `http://localhost:8000` y `http://127.0.0.1:8000`.

## Catálogo académico y postulaciones para tutor

Las migraciones registran carreras, semestres y materias. Ingeniería en Sistemas de Información queda registrada sin semestres ni materias hasta contar con su plan de estudios. `GET /api/tutoring/options` entrega a cada estudiante únicamente las materias y los tutores de su carrera; el API vuelve a validar la carrera al crear la solicitud. `GET /api/tutoring/profile` permite al tutor consultar el plan de su carrera, las materias que seleccionó en su postulación y los temas que indicó dominar.

Desde el catálogo administrativo, `POST /api/admin/carreras` admite opcionalmente `semestres`, una lista de objetos con `numero` y `materias` (nombres de materias). `PUT /api/admin/carreras/{id}` acepta el mismo campo para reemplazar el plan existente y completar o corregir semestres y materias. Las solicitudes anteriores conservan el nombre de la materia aunque se retire del catálogo.

Un estudiante consulta el estado de su postulación con `GET /api/tutoring/application` y la envía con `POST /api/tutoring/application`, usando multipart/form-data y los campos `kardex` (PDF, JPG o PNG; máximo 10 MB), `materias[]` (uno o más identificadores del plan de su carrera) y `temas_dominados` (máximo 1,000 caracteres). El archivo se conserva en almacenamiento privado y la API valida que las materias pertenezcan a la carrera asociada al perfil. El administrador revisa el kardex y la oferta propuesta con `GET /api/admin/tutor-applications`, descarga el kardex mediante `GET /api/admin/tutor-applications/{id}/kardex` y decide con `PATCH /api/admin/tutor-applications/{id}` enviando `decision` como `APROBADA` o `RECHAZADA`. Solo una aprobación cambia el rol del estudiante a tutor; un rechazo conserva el rol de estudiante. La asignación manual de tutor desde la gestión de usuarios queda bloqueada.

## Solicitudes de tutoría

Ejecuta `php artisan migrate` para crear la tabla `solicitudes_tutoria`. Todas las rutas de solicitudes requieren un token Bearer de Sanctum:

- Estudiante: `GET /api/tutoring/options` devuelve materias activas y tutores; `POST /api/tutoring/requests` crea una solicitud con `tutor_id`, `materia_id`, `fecha_hora` futura y `mensaje` opcional. `GET /api/tutoring/requests` lista solo sus propias solicitudes.
- Tutor: la misma ruta GET devuelve solo las solicitudes asignadas. Puede aceptar una solicitud pendiente si su fecha aún es futura, rechazar solicitudes pendientes y cambiar de `ACEPTADA` a `COMPLETADA`.
- Estudiante: puede cambiar `estado` de `PENDIENTE` a `CANCELADA`; también puede cancelar una solicitud aceptada mientras la fecha solicitada siga en el futuro.
- Estudiante: puede calificar una tutoría `COMPLETADA` una sola vez con un valor del 1 al 5 y un comentario opcional de hasta 1000 caracteres.
- Administrador: la ruta GET devuelve todas las solicitudes para supervisión. El administrador no altera los estados; las decisiones pertenecen al tutor y al estudiante.

La fecha y hora son una propuesta del estudiante: esta versión no comprueba disponibilidad de agenda ni bloquea horarios porque el sistema todavía no cuenta con un catálogo de horarios de tutores.

## Baja de cuentas

El administrador puede dar de baja o reactivar una cuenta con `PATCH /api/admin/users/{idUsuario}/status`, enviando `is_active` como `false` o `true`. La baja lógica conserva el usuario y su historial, revoca sus tokens activos y bloquea futuros inicios de sesión. Un administrador no puede dar de baja su propia cuenta.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
