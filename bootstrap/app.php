<?php

declare(strict_types=1);

use App\Http\Controllers\NoteController;
use App\Http\Controllers\TagController;
use App\Http\Requests\CreateNoteRequest;
use App\Http\Requests\ListNotesRequest;
use App\Http\Requests\TopTagsRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Interfaces\Repositories\NoteRepositoryInterface;
use App\Interfaces\Services\NoteServiceInterface;
use App\Interfaces\Services\TagAnalyticsServiceInterface;
use App\Repositories\NoteJsonRepository;
use App\Services\Note\NoteService;
use App\Services\Note\TagAnalyticsService;
use App\Support\Container;
use App\Support\Helpers\File\JsonStorageHelper;
use App\Support\Http\ExceptionRenderer;
use App\Support\Http\Request;
use App\Support\Http\Response;
use App\Support\Http\Router;
use App\Validation\Rules\TagsValidationRule;
use App\Validation\Validators\NoteValidator;

require_once __DIR__ . '/autoload.php';

// require_once, а не require: файл ставит глобальный обработчик ошибок,
// а тесты собирают приложение заново в каждом setUp — обработчики бы стопкой копились.
require_once __DIR__ . '/errors.php';

/**
 * Сборка приложения.
 *
 * @return callable(Request): Response
 */
return static function (?string $storagePath = null): callable {
    $storagePath ??= dirname(__DIR__) . '/storage/notes.json';

    $container = new Container();

    // Классы с конструкторным состоянием регистрируются здесь, а не собираются
    // через new внутри соседних фабрик: класс без регистрации в контейнере —
    // недоделанный класс.
    $container->bind(JsonStorageHelper::class, static fn (): JsonStorageHelper => new JsonStorageHelper($storagePath));
    $container->bind(TagsValidationRule::class, static fn (): TagsValidationRule => new TagsValidationRule());
    $container->bind(ExceptionRenderer::class, static fn (): ExceptionRenderer => new ExceptionRenderer());

    $container->bind(
        NoteRepositoryInterface::class,
        static fn (Container $c): NoteRepositoryInterface => new NoteJsonRepository($c->get(JsonStorageHelper::class))
    );

    $container->bind(
        NoteServiceInterface::class,
        static fn (Container $c): NoteServiceInterface => new NoteService($c->get(NoteRepositoryInterface::class))
    );

    $container->bind(
        TagAnalyticsServiceInterface::class,
        static fn (Container $c): TagAnalyticsServiceInterface => new TagAnalyticsService(
            $c->get(NoteRepositoryInterface::class)
        )
    );

    $container->bind(
        NoteValidator::class,
        static fn (Container $c): NoteValidator => new NoteValidator($c->get(TagsValidationRule::class))
    );
    $container->bind(
        CreateNoteRequest::class,
        static fn (Container $c): CreateNoteRequest => new CreateNoteRequest($c->get(NoteValidator::class))
    );
    $container->bind(
        UpdateNoteRequest::class,
        static fn (Container $c): UpdateNoteRequest => new UpdateNoteRequest($c->get(NoteValidator::class))
    );
    $container->bind(ListNotesRequest::class, static fn (): ListNotesRequest => new ListNotesRequest());
    $container->bind(TopTagsRequest::class, static fn (): TopTagsRequest => new TopTagsRequest());

    $container->bind(NoteController::class, static fn (Container $c): NoteController => new NoteController(
        noteService: $c->get(NoteServiceInterface::class),
        createNoteRequest: $c->get(CreateNoteRequest::class),
        updateNoteRequest: $c->get(UpdateNoteRequest::class),
        listNotesRequest: $c->get(ListNotesRequest::class),
    ));

    $container->bind(TagController::class, static fn (Container $c): TagController => new TagController(
        tagAnalyticsService: $c->get(TagAnalyticsServiceInterface::class),
        topTagsRequest: $c->get(TopTagsRequest::class),
    ));

    $router = new Router();

    $router->add('GET', '/api/v1/notes', static function (Request $request) use ($container): Response {
        /** @var NoteController $controller */
        $controller = $container->get(NoteController::class);

        return $controller->index($request);
    });

    $router->add('POST', '/api/v1/notes', static function (Request $request) use ($container): Response {
        /** @var NoteController $controller */
        $controller = $container->get(NoteController::class);

        return $controller->create($request);
    });

    $router->add('GET', '/api/v1/notes/{id}', static function (Request $request, array $parameters) use ($container): Response {
        /** @var NoteController $controller */
        $controller = $container->get(NoteController::class);

        return $controller->show($parameters['id']);
    });

    $router->add('PUT', '/api/v1/notes/{id}', static function (Request $request, array $parameters) use ($container): Response {
        /** @var NoteController $controller */
        $controller = $container->get(NoteController::class);

        return $controller->replace($request, $parameters['id']);
    });

    $router->add('DELETE', '/api/v1/notes/{id}', static function (Request $request, array $parameters) use ($container): Response {
        /** @var NoteController $controller */
        $controller = $container->get(NoteController::class);

        return $controller->delete($parameters['id']);
    });

    $router->add('GET', '/api/v1/tags/top', static function (Request $request) use ($container): Response {
        /** @var TagController $controller */
        $controller = $container->get(TagController::class);

        return $controller->top($request);
    });

    return static function (Request $request) use ($router, $container): Response {
        /** @var ExceptionRenderer $renderer */
        $renderer = $container->get(ExceptionRenderer::class);

        try {
            return $router->dispatch($request);
        } catch (Throwable $exception) {
            return $renderer->render($exception);
        }
    };
};
