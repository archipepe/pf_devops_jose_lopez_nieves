<?php

namespace App\Security;

use App\Entity\Carrito;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use App\Entity\User;
use App\Service\CarritoService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginFormAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private UserRepository $userRepository;
    private RouterInterface $router;
    private CarritoService $carritoService;
    
    public function __construct(UserRepository $userRepository, RouterInterface $router, CarritoService $carritoService)
    {
        $this->userRepository = $userRepository;
        $this->router = $router;
        $this->carritoService = $carritoService;
    }

    public function supports(Request $request): ?bool
    {
        return ($request->getPathInfo() === '/login' && $request->isMethod('POST'));
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                // optionally pass a callback to load the User manually
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                if (!$user) {
                    throw new UserNotFoundException();
                }
                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Fusionar el carrito anónimo con el del usuario al hacer login
        $this->carritoService->fusionarCarritoAlLogin($request);

        // Redirigir al usuario a la página que estaba intentando acceder antes de iniciar sesión, o a la página de inicio si no hay una URL de referencia.
        // Target path no será nulo cuando el usuario intente acceder a una página protegida sin estar autenticado, y se le redirija al login. En ese caso, Symfony guarda la URL original en la sesión para redirigir después del login.
        // Útil para redirigir al checkout si venía de él y no tenía sesión.
        $targetPath = $request->getSession()->get('_security.' . $firewallName . '.target_path');
        
        if (!$targetPath) {
            $targetPath = $this->router->generate('index');
        }
        
        return new RedirectResponse($targetPath);

        // Forzar redirigir siempre a la página de inicio u otra página después del login exitoso
        // return new RedirectResponse(
        //     $this->router->generate('index')
        // );

        // Si no quieres redirigir a una página específica, simplemente devuelve null para continuar con la solicitud original. Se quedará en la página de login, lo que no tiene sentido
        // return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $request->getSession()->set(Security::LAST_USERNAME, $request->request->get('email', ''));

        return new RedirectResponse(
            $this->router->generate('app_login')
        );
    }

   public function start(Request $request, AuthenticationException $authException = null): Response
   {
       /*
        * If you would like this class to control what happens when an anonymous user accesses a
        * protected page (e.g. redirect to /login), uncomment this method and make this class
        * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
        *
        * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
        */
        return new RedirectResponse(
            $this->router->generate('app_login')
        );
   }
}
