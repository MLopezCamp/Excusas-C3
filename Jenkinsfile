pipeline {
    agent any

    environment {
        DOCKERHUB_CREDENTIALS = credentials('dockerhub-cred')
        DOCKERHUB_USER = "${DOCKERHUB_CREDENTIALS_USR}"
        DOCKERHUB_PASS = "${DOCKERHUB_CREDENTIALS_PSW}"
        IMAGE_NAME = "mlopezcamp/excusas-c3"
        BRANCH = "master"
        LAST_COMMIT_FILE = ".last_commit"
    }

    stages {
        stage('Checkout SCM') {
            steps {
                script {
                    checkout scm

                    def currentCommit = sh(script: "git rev-parse HEAD", returnStdout: true).trim()
                    def lastCommit = fileExists(LAST_COMMIT_FILE) ? readFile(LAST_COMMIT_FILE).trim() : ""

                    if (lastCommit == "") {
                        echo "Primer despliegue detectado. Se construirá y subirá la imagen."
                        env.NO_CHANGES = "false"
                    } else if (currentCommit == lastCommit) {
                        echo "No hay nuevos commits desde el último despliegue (${lastCommit})."
                        env.NO_CHANGES = "true"
                    } else {
                        echo "Cambios detectados: nuevo commit ${currentCommit}"
                        env.NO_CHANGES = "false"
                    }

                    env.CURRENT_COMMIT = currentCommit
                }
            }
        }

        stage('Build Docker Image') {
            when { expression { env.NO_CHANGES == "false" } }
            steps {
                script {
                    def dateTag = sh(script: "date +%Y%m%d", returnStdout: true).trim()
                    def shortCommit = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    env.IMAGE_TAG = "${dateTag}-${shortCommit}"

                    echo "Construyendo imagen: ${IMAGE_NAME}:${IMAGE_TAG}"
                    sh "docker build -t ${IMAGE_NAME}:${IMAGE_TAG} ."
                }
            }
        }

        stage('Login to DockerHub') {
            when { expression { env.NO_CHANGES == "false" } }
            steps {
                script {
                    echo "Iniciando sesión en DockerHub..."
                    sh "echo ${DOCKERHUB_PASS} | docker login -u ${DOCKERHUB_USER} --password-stdin"
                }
            }
        }

        stage('Push to DockerHub') {
            when { expression { env.NO_CHANGES == "false" } }
            steps {
                script {
                    echo "Subiendo imagen ${IMAGE_NAME}:${IMAGE_TAG}..."
                    sh "docker push ${IMAGE_NAME}:${IMAGE_TAG}"

                    echo "Etiquetando como latest"
                    sh """
                    docker tag ${IMAGE_NAME}:${IMAGE_TAG} ${IMAGE_NAME}:latest
                    docker push ${IMAGE_NAME}:latest
                    """
                }
            }
        }

        stage('Actualizar commit desplegado') {
            when { expression { env.NO_CHANGES == "false" } }
            steps {
                script {
                    echo "Guardando commit desplegado: ${env.CURRENT_COMMIT}"
                    writeFile file: LAST_COMMIT_FILE, text: env.CURRENT_COMMIT
                }
            }
        }

        stage('Clean up local images') {
            when { expression { env.NO_CHANGES == "false" } }
            steps {
                script {
                    echo "Limpiando imágenes locales..."
                    sh "docker rmi ${IMAGE_NAME}:${IMAGE_TAG} || true"
                    sh "docker rmi ${IMAGE_NAME}:latest || true"
                }
            }
        }
    }

    post {
        always {
            echo "Pipeline finalizado: ${currentBuild.result}"
        }
    }
}
