# Build Vite SPA, then serve via Nginx (same-origin /api proxy in compose).
FROM node:20-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
# Prefer clean install; fall back if lock drifts on deploy hosts.
RUN npm ci || npm install
COPY frontend/ ./
# Relative API path so browser talks to the same host (Nginx proxies /api).
ARG VITE_API_URL=/api
ARG VITE_APP_NAME=IRCUB
ENV VITE_API_URL=$VITE_API_URL \
    VITE_APP_NAME=$VITE_APP_NAME
RUN npm run build

FROM nginx:1.27-alpine
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
