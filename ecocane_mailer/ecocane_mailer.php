<?php
/**
 * Plugin Name: eCoCane Mailer
 * Description: Отправка готового HTML-шаблона eCoCane через wp_mail() и настроенный SMTP.
 * Version: 1.0.0
 * Author: eCoCane
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ECoCane_Mailer {
    const OPTION_TEMPLATE = 'ecocane_mailer_template_path';
    const OPTION_LOG = 'ecocane_mailer_log';
    const MENU_SLUG = 'ecocane-mailer';
    const DEFAULT_TEMPLATE = 'eCoCane_mail/index.html';
    const FROM_EMAIL = 'info@eco-cane.group';
    const FROM_NAME = 'eCo-Cane.GROUP';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_ecocane_mailer_send_batch', array($this, 'ajax_send_batch'));
        add_action('admin_post_ecocane_mailer_preview', array($this, 'preview_template'));
    }

    public function register_menu() {
        add_menu_page(
            'eCoCane — Рассылки',
            'Рассылки eCoCane',
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_page'),
            'dashicons-email-alt',
            58
        );
    }

    public function register_settings() {
        register_setting(
            'ecocane_mailer_settings',
            self::OPTION_TEMPLATE,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_template_path'),
                'default' => self::DEFAULT_TEMPLATE,
            )
        );
    }

    public function sanitize_template_path($value) {
        $value = trim((string) $value);
        $value = str_replace('\\', '/', $value);
        $value = ltrim($value, '/');

        // Путь задаётся относительно корня WordPress. Запрещаем обход каталогов.
        $parts = explode('/', $value);
        if (in_array('..', $parts, true) || $value === '') {
            add_settings_error(self::OPTION_TEMPLATE, 'invalid_path', 'Укажите относительный путь внутри WordPress без сегментов "..".');
            return self::DEFAULT_TEMPLATE;
        }

        return $value;
    }

    private function template_path() {
        $relative = get_option(self::OPTION_TEMPLATE, self::DEFAULT_TEMPLATE);
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        if ($relative === '' || in_array('..', explode('/', $relative), true)) {
            return false;
        }

        $root = realpath(ABSPATH);
        $candidate = realpath(ABSPATH . $relative);

        if (!$root || !$candidate || !is_file($candidate) || !is_readable($candidate)) {
            return false;
        }

        $prefix = trailingslashit($root);
        if (strpos($candidate, $prefix) !== 0) {
            return false;
        }

        return $candidate;
    }

    private function template_html() {
        $path = $this->template_path();
        if (!$path) {
            return false;
        }

        $html = file_get_contents($path);
        return ($html !== false && trim($html) !== '') ? $html : false;
    }

    public function preview_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостаточно прав.', 403);
        }
        check_admin_referer('ecocane_mailer_preview');

        $html = $this->template_html();
        if (!$html) {
            wp_die('Не удалось прочитать HTML-шаблон. Проверьте путь в настройках плагина.', 404);
        }

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        echo $html; // Шаблон — локальный файл, доступный только администратору через nonce.
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $html = $this->template_html();
        $template_path = get_option(self::OPTION_TEMPLATE, self::DEFAULT_TEMPLATE);
        $preview_url = wp_nonce_url(
            admin_url('admin-post.php?action=ecocane_mailer_preview'),
            'ecocane_mailer_preview'
        );
        $log = get_option(self::OPTION_LOG, array());
        ?>
        <div class="wrap">
            <h1>eCoCane — Email-рассылка</h1>
            <p>Отправка использует готовый HTML-файл без повторной вёрстки. Доставка выполняется через <code>wp_mail()</code> и подключённый SMTP-плагин FluentSMTP.</p>

            <div class="notice notice-info inline">
                <p><strong>Отправитель:</strong> <?php echo esc_html(self::FROM_EMAIL); ?>. Письма отправляются адресатам по одному, без раскрытия списка получателей.</p>
            </div>

            <h2>HTML-шаблон</h2>
            <form method="post" action="options.php">
                <?php settings_fields('ecocane_mailer_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_TEMPLATE); ?>">Путь к HTML-файлу</label></th>
                        <td>
                            <input
                                name="<?php echo esc_attr(self::OPTION_TEMPLATE); ?>"
                                id="<?php echo esc_attr(self::OPTION_TEMPLATE); ?>"
                                type="text"
                                class="regular-text code"
                                value="<?php echo esc_attr($template_path); ?>"
                            >
                            <p class="description">Путь относительно корня WordPress. По умолчанию: <code><?php echo esc_html(self::DEFAULT_TEMPLATE); ?></code></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Сохранить путь'); ?>
            </form>

            <?php if ($html) : ?>
                <p>
                    <span class="dashicons dashicons-yes-alt" style="color:#16803c"></span>
                    HTML-файл найден и доступен для чтения.
                    <a class="button button-secondary" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener">Предпросмотр письма</a>
                </p>
            <?php else : ?>
                <div class="notice notice-error inline"><p><strong>HTML-файл не найден.</strong> Проверьте, что файл находится по указанному пути внутри корня WordPress.</p></div>
            <?php endif; ?>

            <hr>
            <h2>Отправка письма</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ecocane-subject">Тема письма</label></th>
                    <td><input type="text" id="ecocane-subject" class="regular-text" value="eCo-Cane.GROUP — экологичная посуда и упаковка" maxlength="200"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ecocane-test-email">Тестовый адрес</label></th>
                    <td>
                        <input type="email" id="ecocane-test-email" class="regular-text" placeholder="Ваш email">
                        <p class="description">Сначала отправьте письмо себе и проверьте его отображение в почтовом клиенте.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ecocane-recipients">Получатели</label></th>
                    <td>
                        <textarea id="ecocane-recipients" rows="9" class="large-text code" placeholder="client1@example.com&#10;client2@example.com&#10;client3@example.com"></textarea>
                        <p class="description">Один адрес на строку. Можно также разделять адреса запятыми или точками с запятой. Максимум 50 уникальных адресов за одну кампанию.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-secondary" id="ecocane-test-send" <?php disabled(!$html); ?>>Отправить тестовое письмо</button>
                <button type="button" class="button button-primary" id="ecocane-campaign-send" <?php disabled(!$html); ?>>Начать рассылку</button>
            </p>
            <p>
                <label><input type="checkbox" id="ecocane-consent"> Я подтверждаю, что у меня есть законное основание для отправки писем всем указанным адресатам, включая необходимые согласия на рекламные сообщения.</label>
            </p>
            <div id="ecocane-mailer-status" class="notice inline" style="display:none;"><p></p></div>
            <div id="ecocane-mailer-results" style="max-width:760px;"></div>

            <?php if (!empty($log['time'])) : ?>
                <hr>
                <h2>Последняя кампания</h2>
                <p>Время: <?php echo esc_html($log['time']); ?> · Тема: <?php echo esc_html($log['subject']); ?> · Передано SMTP: <?php echo esc_html((string) $log['sent']); ?> · Ошибок wp_mail: <?php echo esc_html((string) $log['failed']); ?></p>
                <p class="description">Успешный результат wp_mail означает, что WordPress передал письмо почтовому транспорту. Это не подтверждает попадание письма во «Входящие» или его прочтение.</p>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            const nonce = <?php echo wp_json_encode(wp_create_nonce('ecocane_mailer_send')); ?>;
            const statusBox = document.getElementById('ecocane-mailer-status');
            const resultsBox = document.getElementById('ecocane-mailer-results');
            const testButton = document.getElementById('ecocane-test-send');
            const campaignButton = document.getElementById('ecocane-campaign-send');

            function status(message, type) {
                statusBox.style.display = 'block';
                statusBox.className = 'notice inline notice-' + (type || 'info');
                statusBox.querySelector('p').textContent = message;
            }

            function getRecipients() {
                const raw = document.getElementById('ecocane-recipients').value;
                const parts = raw.split(/[\s,;]+/).map(v => v.trim()).filter(Boolean);
                return [...new Set(parts)];
            }

            function setBusy(busy) {
                testButton.disabled = busy;
                campaignButton.disabled = busy;
            }

            async function post(data) {
                const form = new FormData();
                Object.keys(data).forEach(key => {
                    if (Array.isArray(data[key])) {
                        data[key].forEach(value => form.append(key + '[]', value));
                    } else {
                        form.append(key, data[key]);
                    }
                });
                const response = await fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form });
                return await response.json();
            }

            testButton.addEventListener('click', async function () {
                const email = document.getElementById('ecocane-test-email').value.trim();
                const subject = document.getElementById('ecocane-subject').value.trim();
                if (!email || !subject) {
                    status('Укажите тестовый email и тему письма.', 'error');
                    return;
                }
                setBusy(true);
                resultsBox.innerHTML = '';
                status('Отправляем тестовое письмо…', 'info');
                try {
                    const result = await post({
                        action: 'ecocane_mailer_send_batch',
                        nonce: nonce,
                        mode: 'test',
                        test_email: email,
                        subject: subject
                    });
                    if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : 'Ошибка отправки.');
                    status(result.data.message, 'success');
                } catch (error) {
                    status(error.message, 'error');
                } finally {
                    setBusy(false);
                }
            });

            campaignButton.addEventListener('click', async function () {
                const recipients = getRecipients();
                const subject = document.getElementById('ecocane-subject').value.trim();
                const consent = document.getElementById('ecocane-consent').checked;

                if (!subject) return status('Укажите тему письма.', 'error');
                if (!recipients.length) return status('Добавьте хотя бы один email.', 'error');
                if (recipients.length > 50) return status('За одну кампанию можно отправить не более чем 50 уникальным адресатам.', 'error');
                if (!consent) return status('Подтвердите наличие законного основания для рассылки.', 'error');

                const invalid = recipients.filter(email => !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
                if (invalid.length) return status('Некорректные адреса: ' + invalid.join(', '), 'error');

                if (!window.confirm('Отправить готовое HTML-письмо ' + recipients.length + ' адресатам? Каждый получит отдельное письмо.')) return;

                setBusy(true);
                resultsBox.innerHTML = '';
                let sent = 0;
                let failed = 0;
                const batchSize = 5;
                status('Подготовка рассылки…', 'info');

                try {
                    for (let offset = 0; offset < recipients.length; offset += batchSize) {
                        const result = await post({
                            action: 'ecocane_mailer_send_batch',
                            nonce: nonce,
                            mode: 'campaign',
                            recipients: recipients.slice(offset, offset + batchSize),
                            offset: String(offset),
                            subject: subject,
                            consent: '1'
                        });

                        if (!result.success) throw new Error(result.data && result.data.message ? result.data.message : 'Ошибка обработки пакета.');

                        sent += result.data.sent || 0;
                        failed += result.data.failed || 0;
                        status('Обработано ' + Math.min(offset + batchSize, recipients.length) + ' из ' + recipients.length + '. Передано: ' + sent + ', ошибок: ' + failed + '.', 'info');

                        (result.data.results || []).forEach(item => {
                            const row = document.createElement('p');
                            row.textContent = (item.ok ? '✓ ' : '✕ ') + item.email + (item.ok ? ' — передано SMTP' : ' — ошибка отправки');
                            resultsBox.appendChild(row);
                        });
                    }
                    status('Рассылка завершена. Передано SMTP: ' + sent + '; ошибок: ' + failed + '.', failed ? 'warning' : 'success');
                } catch (error) {
                    status('Рассылка остановлена: ' + error.message + '. Уже отправленные письма отменить нельзя.', 'error');
                } finally {
                    setBusy(false);
                }
            });
        })();
        </script>
        <?php
    }

    public function ajax_send_batch() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Недостаточно прав.'), 403);
        }
        check_ajax_referer('ecocane_mailer_send', 'nonce');

        $html = $this->template_html();
        if (!$html) {
            wp_send_json_error(array('message' => 'HTML-шаблон не найден или недоступен для чтения.'), 400);
        }

        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        if ($subject === '' || strlen($subject) > 200) {
            wp_send_json_error(array('message' => 'Укажите корректную тему письма.'), 400);
        }

        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : '';

        if ($mode === 'test') {
            $email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
            if (!is_email($email)) {
                wp_send_json_error(array('message' => 'Укажите корректный тестовый email.'), 400);
            }
            $ok = $this->send_one($email, $subject, $html);
            if (!$ok) {
                wp_send_json_error(array('message' => 'wp_mail() вернул ошибку. Проверьте журнал FluentSMTP.'), 500);
            }
            wp_send_json_success(array('message' => 'Тестовое письмо передано SMTP для адреса ' . $email . '. Проверьте входящие и папку «Спам».'));
        }

        if ($mode !== 'campaign') {
            wp_send_json_error(array('message' => 'Неизвестный режим отправки.'), 400);
        }

        if (empty($_POST['consent'])) {
            wp_send_json_error(array('message' => 'Не подтверждено законное основание рассылки.'), 400);
        }

        $raw_recipients = isset($_POST['recipients']) ? (array) wp_unslash($_POST['recipients']) : array();
        $recipients = array();
        foreach ($raw_recipients as $raw_email) {
            $email = sanitize_email($raw_email);
            if (is_email($email)) {
                $recipients[] = $email;
            }
        }
        $recipients = array_values(array_unique($recipients));

        if (empty($recipients) || count($recipients) > 5) {
            wp_send_json_error(array('message' => 'В одном пакете должно быть от 1 до 5 корректных адресов.'), 400);
        }

        $offset = isset($_POST['offset']) ? max(0, absint($_POST['offset'])) : 0;
        if ($offset === 0) {
            update_option(self::OPTION_LOG, array(
                'time' => current_time('mysql'),
                'subject' => $subject,
                'sent' => 0,
                'failed' => 0,
            ), false);
        }

        $results = array();
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $email) {
            $ok = $this->send_one($email, $subject, $html);
            $results[] = array('email' => $email, 'ok' => $ok);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->update_log($subject, $sent, $failed);

        wp_send_json_success(array(
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ));
    }

    private function send_one($email, $subject, $html) {
        $content_type = function () {
            return 'text/html; charset=UTF-8';
        };
        $from_email = function () {
            return self::FROM_EMAIL;
        };
        $from_name = function () {
            return self::FROM_NAME;
        };

        add_filter('wp_mail_content_type', $content_type);
        add_filter('wp_mail_from', $from_email);
        add_filter('wp_mail_from_name', $from_name);

        $headers = array(
            'Reply-To: ' . self::FROM_NAME . ' <' . self::FROM_EMAIL . '>',
        );

        $ok = wp_mail($email, $subject, $html, $headers);

        remove_filter('wp_mail_content_type', $content_type);
        remove_filter('wp_mail_from', $from_email);
        remove_filter('wp_mail_from_name', $from_name);

        return (bool) $ok;
    }

    private function update_log($subject, $sent, $failed) {
        $old = get_option(self::OPTION_LOG, array());
        $old_sent = isset($old['sent']) ? (int) $old['sent'] : 0;
        $old_failed = isset($old['failed']) ? (int) $old['failed'] : 0;

        update_option(self::OPTION_LOG, array(
            'time' => current_time('mysql'),
            'subject' => $subject,
            'sent' => $old_sent + $sent,
            'failed' => $old_failed + $failed,
        ), false);
    }
}

new ECoCane_Mailer();
