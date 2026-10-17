<?php

namespace XF\ConnectedAccount\ProviderData;

class LinkedinProviderData extends AbstractProviderData
{
	public function getDefaultEndpoint()
	{
		return '/me?projection=(id,localizedFirstName,localizedLastName,profilePicture(displayImage~:playableStreams))';
	}

	public function getProviderKey()
	{
		return $this->requestFromEndpoint('id');
	}

	public function getFormattedName()
	{
		$firstName = $this->requestFromEndpoint('localizedFirstName');
		$lastName = $this->requestFromEndpoint('localizedLastName');

		if ($firstName && $lastName)
		{
			return "$firstName $lastName";
		}

		return null;
	}

	public function getEmail()
	{
		$emailData = $this->requestFromEndpoint('elements', 'GET', '/emailAddress?q=members&projection=(elements*(handle~))');

		return $emailData[0]['handle~']['emailAddress'] ?? null;
	}

	public function getAvatarUrl()
	{
		$imageData = $this->requestFromEndpoint('profilePicture');

		return $imageData['displayImage~']['elements'][1]['identifiers'][0]['identifier'] ?? null;
	}
}
