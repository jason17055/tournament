package dragonfin.tournament;

import java.io.*;
import java.util.*;
import com.fasterxml.jackson.core.*;

public class Player
{
	transient Tournament tournament;
	String name;
	Map<String,String> attributes = new HashMap<String,String>();

	public Player(Tournament tournament)
	{
		this.tournament = tournament;
	}

	public String getName()
	{
		return name;
	}

	public void setName(String newName)
	{
		this.name = newName;
		tournament.dirty = true;
	}

	public void parse(JsonParser in)
		throws IOException
	{
		if (in.getCurrentToken() != JsonToken.START_OBJECT)
			throw new JsonParseException("player: not an object",
				in.getCurrentLocation());
		while (in.nextToken() != JsonToken.END_OBJECT)
		{
			String fieldName = in.getCurrentName();
			in.nextToken();
			if (fieldName.equals("name")) {
				name = in.getText();
			}
			else {
				// treat as a custom attribute
				attributes.put(fieldName, in.getText());
				in.skipChildren();
			}
		}
	}

	public void write(JsonGenerator out)
		throws IOException
	{
		out.writeStartObject();
		out.writeStringField("name", name);

		for (String s : attributes.keySet()) {
			out.writeStringField(s, attributes.get(s));
		}

		out.writeEndObject();
	}
}
