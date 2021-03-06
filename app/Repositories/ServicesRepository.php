<?php

namespace App\Repositories;

use App\Models\Service;
use App\Models\User;

class ServicesRepository extends EloquentRepository
{
    /**
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        parent::__construct($service);
    }

    /**
     * @param $serviceId
     * @return mixed
     */
    public function getExpandedServices($serviceId = null, $offset = null, $limit = null)
    {
        if ($serviceId) {
            return $this->getExpandedServicesQuery($serviceId)->first();
        }

        return $this->getExpandedServicesQuery(null, $offset, $limit)->get();
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function getExpandedServiceForUser($userId, $offset, $limit)
    {
        if (empty($userId)) {
            return;
        }

        return $this->getExpandedServiceForUserQuery($userId, $offset, $limit)->get();
    }

    /**
     * Return all services with their channels and openinghours (without the attached calendars)
     *
     * has_missing_oh =     Missing calender(s)
     * has_inactive_oh =    Missing active calender(s)
     *
     * @param  int $serviceId
     * @return Collection
     */
    private function getExpandedServicesQuery($serviceId = null, $offset = 0, $limit = null)
    {
        $rawSelect = \DB::raw("services.*,
            count(channelId) countChannels,
            if(group_concat(missingOH) like '%1%', 1, 0) has_missing_oh,
            if(group_concat(activeOH) like '%0%', 1, 0) has_inactive_oh, min(date.end_date) as end_date");

        $rawSubQuery = \DB::raw("(select  c.service_id, c.id channelId ,
                if(oh.id is null, true, false) missingOH,
                if(group_concat((select oh.start_date < curdate() and oh.end_date > curdate())) like '%1%', 1, 0) activeOH
                from channels c left join openinghours oh on c.id = oh.channel_id
                where c.deleted_at is null
                group by c.id) as tmp");

        $rawEndDateQuery = \DB::raw("(select c.service_id, max(oh.end_date) as end_date
                from channels as c
                left join openinghours as oh on oh.channel_id = c.id
                where c.deleted_at is null
                group by c.id) as date");

        $query = \DB::table('services')
            ->select($rawSelect)
            ->leftJoin($rawSubQuery, 'services.id', '=', 'tmp.service_id')
            ->leftJoin($rawEndDateQuery, 'services.id', '=', 'date.service_id')
            ->groupBy('services.id')
            ->orderBy('services.draft', 'ASC')
            ->orderBy('services.updated_at', 'DESC')
            ->skip($offset);

        if ($limit === null){
            $query->take(Service::all()->count() - $offset > 0 ? Service::all()->count() - $offset : 0);
        }
        else {
            $query->take($limit);
        }

        if ($serviceId) {
            $query->where('id', $serviceId);
        }

        return $query;
    }

    /**
     * Return a specific service with linked channels
     *
     * @param  int $serviceId
     *
     * @return \App\Repositories\Collection
     */
    private function getExpandedServiceForUserQuery($userId, $offset, $limit)
    {
        if (empty($userId)) {
            return;
        }

        $query = $this->getExpandedServicesQuery(null, $offset, $limit);

        $query->join('user_service_role', 'services.id', '=', 'user_service_role.service_id')
            ->where('user_service_role.user_id', $userId);

        return $query;
    }

    /**
     * Return a specific service with linked channels
     *
     * @param  int $serviceId
     * @return array
     */
    public function getById($serviceId)
    {
        $service = $this->model->find($serviceId);

        if (empty($service)) {
            return [];
        }

        return $this->expandService($service);
    }

    /**
     * Get all services where the user, based on the passed user ID, is part of
     *
     * @param integer $userId
     *
     * @return Collection
     */
    public function getForUser($userId)
    {
        return $this->model->whereHas('users', function ($query) use ($userId) {
            $query->where('id', '=', $userId);
        })->get()->map([$this, 'expandService']);
    }

    /**
     * Get the complete service detail
     * @todo check or these properties should be dynamic or static on the MODEL
     * @param  integer $userId
     * @return array
     */
    public function expandService($service)
    {
        $result = $service->toArray();

        if ($service->channels->count() > 0) {
            $result['c'] = [];

            foreach ($service->channels as $channel) {
                if ($channel->openinghours->count() == 0) {
                    $result['c']['has_missing_oh'] = true;
                }

                foreach ($channel->openinghours as $openinghours) {
                    if (!$openinghours->active) {
                        $result['c']['has_inactive_oh'] = true;
                        break;
                    }
                }
            }
        }

        //todo do the same for users
        $users = app('UserRepository');

        // Get all of the users for the service
        $result['users'] = $users->getAllInService($result['id']);

        return $result;
    }

    /**
     * Get the channels for the service
     *
     * @return array
     */
    public function getChannels()
    {
        $result = [];
        // Get all of the channels for the service
        foreach ($this->model->channels as $channel) {
            $tmpChannel = $channel->toArray();
            $tmpChannel['openinghours'] = [];

            foreach ($channel->openinghours as $openinghours) {
                $instance = $openinghours->toArray();
                $tmpChannel['openinghours'][] = $instance;
            }

            $result[] = $tmpChannel;
        }

        return $result;
    }
}
