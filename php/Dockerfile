FROM php:8-cli

# Обновляем
RUN apt-get update && \
    apt-get install -y --no-install-recommends

WORKDIR /app

# Установка расширения zip для работы с composer
RUN apt-get install -y zip unzip

# Установка расширения git для работы с composer
RUN apt-get install -y git

# Установка Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Очистка папки logs при запуске контейнера
RUN rm -rf /logs/*

# Установка часового пояса Москвы
RUN ln -sf /usr/share/zoneinfo/Europe/Moscow /etc/localtime

# Такой запук, нам нужен, чтоб писать вывод ошибок в лог
#  && tail -f /dev/null позволяет не завершать докер после выполнения
CMD ["sh", "-c", "php /app/runner.php >> /app/logs/error_runner.log 2>&1 && tail -f /dev/null"]
