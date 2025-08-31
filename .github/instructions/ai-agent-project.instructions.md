---
applyTo: '**'
---
we want to setup a system which can serve multiple organizations as AI support agent.
for this we want to use mistral 7B quantized to 8bit.
and the depending software alongwith it.
we have also installed qdrant vector database on server and can be accessed at 127.0.0.1. you can refer to qdrant.md
we also need to build backend api which can be used in any website to add new info in the db as well as a UI pages to manage the modal.
I think we can use ollama alongwith it or any other modal or software you think right.
just to give you an idea how we want to use it is like

diagnostic clinic - providing many tests. so we will store the data in vector db or somewhere as you suggest like the test name - a description of the test, cost, requirement for thest test, timing when this test can be done
faqs - standard queries which we think visitor to the site may ask and reply it in a natural language.
we don't want voice chat for now.
like the example I have given we can have some similar data but different structure for another organization.
so our modal should be able to not only read content from vector but what is stored in mysql or whatever is stored in mysql we can sync to vector - the info from website may not be very long but can be 50-100 pages or about say max 500-1000 products.

the workspace is connected to remote server which is ubuntu 22.04
current workspace is a www-data limited user - so we cannot run systemctl command here
the workspace weburl is https://ai-chat.support

so final structure.
Laravel will act as your main web application and frontend.
Laravel will interact with the Python FastAPI backend via HTTP API calls (REST endpoints).
FastAPI will handle:
Generating embeddings (using Ollama or other models)
Communicating with Qdrant (vector DB)
Interacting with the LLM (Ollama/Mistral 7B)
FastAPI will expose endpoints for Laravel to:
Add/query data in Qdrant
Generate embeddings
Get LLM responses

data structure in qdrant
Example Structure (Gupta Diagnostics)


in laravel - always create livewire components for frontend interactions and not standard controller and view.
we can create controller for interaction with fastapi if needed.
