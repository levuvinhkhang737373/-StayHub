FROM nginx:alpine

RUN rm -f /etc/nginx/conf.d/default.conf

COPY app.conf /etc/nginx/conf.d/app.conf
COPY frontend.conf /etc/nginx/conf.d/frontend.conf

RUN nginx -t
