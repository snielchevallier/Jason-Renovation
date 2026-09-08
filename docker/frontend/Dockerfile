# Node.js 22 LTS. Execute Next.js en mode developpement (hot reload).
FROM node:22-alpine

# pnpm est fourni via corepack (inclus avec Node).
RUN corepack enable

WORKDIR /app

# En developpement, le dossier ./frontend de l'hote est monte sur /app
# (voir compose.yaml) : le code modifie est pris en compte sans rebuild.
CMD ["pnpm", "dev"]
