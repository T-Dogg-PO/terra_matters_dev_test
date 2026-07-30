FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm install --no-audit --no-fund

COPY . /app

EXPOSE 3000

CMD ["npm", "run", "dev"]
