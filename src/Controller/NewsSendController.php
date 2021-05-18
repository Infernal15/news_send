<?php

namespace Drupal\news_send\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Class NewsSendController contain functions for edit and delete information.
 */
class NewsSendController extends ControllerBase {

  /**
   * Connection to database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * Constructor for NewsSendController.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): NewsSendController {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Changes the daily param in the module table.
   */
  public function changeMailerType(string $uuid): array {
    if (isset($uuid)) {
      $query = $this->connection->select('news_send', 'ns')
        ->condition('uuid', $uuid);
      $query->fields('ns');
      $user = $query->execute()->fetch();
      if ($user) {
        $daily = $user->daily;

        $query = $this->connection->upsert('news_send')->fields([
          'email' => $user->email,
          'uuid' => $uuid,
          'daily' => !$daily ? '1' : '0',
        ]);

        $query->key('uuid');
        $query->execute();

        if ($daily === '1') {
          $plan = "<span>every hour</span>";
        }
        else {
          $plan = "<span>daily</span>";
        }

        $respond_string = "<h1>Change email mailing</h1><p>Thank you for being" .
          " with us. Your mailing type has changed to " . $plan .
          ". Changes are applied already.</p>";
      }
      else {
        $respond_string = "<h2>Oops, there was an unknown error.</h2>";
      }
      $build['list'] = [
        '#theme' => 'respond',
        '#fields' => $respond_string,
      ];
      return $build;
    }
    return [];
  }

  /**
   * Delete information from the database.
   */
  public function deleteMailer(string $uuid):array {
    if (isset($uuid)) {
      $query = $this->connection->delete('news_send')
        ->condition('uuid', $uuid);
      $query->execute();

      $build['list'] = [
        '#theme' => 'respond',
        '#fields' => 'Your subscription has been canceled. Sorry to see you' .
        ' go.',
      ];
      return $build;
    }
    return [];
  }

}
