#!/bin/bash

# AI Backend Setup Script
# Run this script to ensure all dependencies are properly installed

echo "Setting up AI Backend dependencies..."

# Navigate to ai_backend directory
cd /var/www/clients/client1/web64/web/ai_backend

# Activate virtual environment
source venv/bin/activate

# Install/upgrade pip
pip install --upgrade pip

# Install requirements
pip install -r requirements.txt

echo "Dependencies installed successfully!"

# Test if FastAPI can be imported
python -c "import fastapi; print('FastAPI is available')"
python -c "import uvicorn; print('Uvicorn is available')"
python -c "import httpx; print('HTTPX is available')"
python -c "import qdrant_client; print('Qdrant client is available')"

echo "All dependencies verified!"
echo "You can now set up the systemctl services using the instructions in SERVICES_SETUP.md"
