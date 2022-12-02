# storj-deployment
Full compose multinode deployment for storj-node with exporters and grafana.

This implementation aims to gather all available information in single place and make deployment as simple as possible.

## Deployment example
UI

![UI](https://user-images.githubusercontent.com/23486452/204962205-8b505289-96b8-4e36-96ee-efa6da232a6b.png)

storj-node stack

![storj-node stack](https://user-images.githubusercontent.com/23486452/204962751-053a1569-d5f5-46c5-ba09-adae46478fa6.png)

storj-ui stack

![storj-ui stack](https://user-images.githubusercontent.com/23486452/204962782-7c99ee01-1c15-4b78-b339-d2b60f52e05a.png)

Portainer

![image](https://user-images.githubusercontent.com/23486452/204962666-96150bbc-0f1c-453e-9387-06a6ad298973.png)


## Per node compose deployment
* storj-node with log to file
* preconfigured zksync
* logrotate for storj-node
* storj exporter
* storj log exporter
* storj updater
* running on shared dedicated internal network

## Credits
Implementation includes following:
* https://github.com/storj/storj
* https://github.com/anclrii/Storj-Exporter
* https://github.com/anclrii/Storj-Exporter-dashboard
* https://github.com/kevinkk525/storj-log-exporter


# Prepare for deployment

## Build storj-log-exporter docker image
```
cd ~
git clone https://github.com/kevinkk525/storj-log-exporter
cd storj-log-exporter
sudo docker build -t storj-log-exporter .
```

# Node deployment
Example for node "01". Increment number as needed.

## Prepare storj node implementation
Get your auth token and Generate node identity as normal with name nodeXX, replace XX with node number. Example: node01
https://www.storj.io/host-a-node

Linux example for node01:
```
cd ~
wget https://github.com/storj/storj/releases/latest/download/identity_linux_amd64.zip
unzip -o identity_linux_amd64.zip
chmod +x identity
./identity create node01
./identity authorize node01 <email:characterstring>
grep -c BEGIN ~/.local/share/storj/identity/node01/ca.cert
grep -c BEGIN ~/.local/share/storj/identity/node01/identity.cert
```
*grep should result in 2 and 3 respectively.*

Port forward 140XX, replace XX with node number. Example: 14001

## Initialize storj node
```
docker run --rm -e SETUP="true" \
    --user $(id -u):$(id -g) \
    --mount type=bind,source="/root/.local/share/storj/identity/node01",destination=/app/identity \
    --mount type=bind,source="/mnt/node01",destination=/app/config \
    --name storj-node01 storjlabs/storagenode:latest
```

## Deploy storj node
Checkout storj-node stack for new node
```
cd ~
rm /mnt/node01/config.yaml
svn checkout https://github.com/tomaae/storj-deployment.git/trunk/storj-nodeXX storj-node01
```

Edit stack configuration:
```
sed -i 's/XX/01/g' ./storj-node01/.env
```

Edit storj-node01/.env attributes as needed:
```
NODE_ID=<2 number node id>
NODE_EMAIL=<contact email>
NODE_EXTERNAL=<external fqdn>:150XX
NODE_STORAGE=<storj node size>
NODE_WALLET=<wallet address>
NODE_MNT=<storj node config mount point>
NODE_IDENTITY=<storj node iudentity directory>
```
*node ID, ports and NODE_IDENTITY should be preconfigured correctly*

Deploy node
```
cd storj-node01
docker compose up -d
```

# Grafana deployment
Checkout storj-ui stack
```
cd ~
svn checkout https://github.com/tomaae/storj-deployment.git/trunk/storj-ui
```

Edit stack configuration storj-ui/.env
```
PROMETHEUS_MOUNT=<ui mount pointmount point>/prometheus
GRAFANA_MOUNT=<ui mount point>/grafana

GF_SECURITY_ADMIN_PASSWORD=<password>
GF_USERS_ALLOW_SIGN_UP=false
GF_INSTALL_PLUGINS=yesoreyeram-boomtable-panel

DOCKER_HOST_HOSTNAME=<docker host fqdn>
```

Edit storj-ui/prometheus.yml, append block for each node, replace XX with node ID
```
  - job_name: storj-nodeXX
    scrape_interval: 60s
    scrape_timeout: 20s
    metrics_path: /
    static_configs:
      - targets: ["storj-exporterXX:9651", "storj-log-exporterXX:9144"]
        labels:
          instance: "nodeXX"
```

Deploy storj-ui
```
cd storj-ui
docker compose up -d
```

Import grafana dashboard:

https://raw.githubusercontent.com/tomaae/storj-deployment/main/storj-ui/dashboard_exporter_combined.json

# multinode deployment
Optionally, if you want to also deploy official multinode

Generate identity for multinode:
```
cd ~
./identity create multinode --difficulty 10
```

Deploy docker image:
```
docker run -d --restart unless-stopped \
    --user $(id -u):$(id -g) \
    -p 10000:15002/tcp \
    --network storj-bridge
    --mount type=bind,source="/root/.local/share/storj/identity/multinode/ca.key",destination=/app/identity \
    --mount type=bind,source="/mnt/multinode",destination=/app/config \
    --name storj-multinode storjlabs/multinode:latest
```

Issue API key:
```
docker exec -it storj-node01 ./storagenode issue-apikey --identity-dir /app/identity --config-dir /app/config --log.output stderr
```
*Change node01 to appropriate node number*

multinode UI will be available on port 10000
When importing new nodes, use internal network addresses instead of public:
```
Public IP Address:
storj-node01:28967
```
